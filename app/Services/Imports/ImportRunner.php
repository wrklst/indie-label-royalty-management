<?php

namespace App\Services\Imports;

use App\Enums\ImportStatus;
use App\Enums\TransactionType;
use App\Models\Contract;
use App\Models\DataImport;
use App\Models\Item;
use App\Models\Label;
use App\Models\Payee;
use App\Models\Transaction;
use App\Services\Imports\Readers\ReaderFactory;
use App\Support\Decimal;
use App\Support\Formats;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Runs an importer over an uploaded file and stores the transactions.
 */
class ImportRunner
{
    private const int CHUNK = 500;

    private const int MAX_STORED_ERRORS = 200;

    public function __construct(
        private readonly ImporterRegistry $importers,
        private readonly ReaderFactory $readers,
    ) {}

    /**
     * Import the file attached to a pending data import.
     *
     * The whole file is imported in one database transaction: either every
     * valid line ends up in the ledger or none does.
     */
    public function run(DataImport $import, Label $label): DataImport
    {
        $importer = $this->importers->find($import->importer);

        try {
            if ($importer === null) {
                throw new ImportException("Unknown importer [{$import->importer}].");
            }

            $reader = $this->readers->open(Storage::disk('local')->path($import->file_path));

            DB::transaction(function () use ($import, $label, $importer, $reader): void {
                $this->process($import, $label, fn (): iterable => $importer->rows($reader, $label, $import->options ?? []));
            });
        } catch (ImportException $exception) {
            $import->update([
                'status' => ImportStatus::Failed,
                'errors' => [['line' => null, 'message' => $exception->getMessage()]],
                'errors_count' => 1,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $import->update([
                'status' => ImportStatus::Failed,
                'errors' => [['line' => null, 'message' => 'The import stopped unexpectedly and nothing was saved: '.$exception->getMessage()]],
                'errors_count' => 1,
            ]);
        }

        return $import->refresh();
    }

    /**
     * @param  Closure(): iterable<int, ImportRow|ImportRowError>  $rows  Reads the file from its first line each time it is called.
     */
    private function process(DataImport $import, Label $label, Closure $rows): void
    {
        $matcher = new CatalogMatcher($label, $import->id);
        $payees = Payee::query()->where('label_id', $label->id)->pluck('id', 'name')
            ->mapWithKeys(fn (int $id, string $name): array => [Identifiers::nameKey($name) => $id])->all();
        $itemIds = Item::query()->where('label_id', $label->id)->pluck('id')->flip();
        $contractIds = Contract::query()->where('label_id', $label->id)->pluck('id')->flip();
        $options = $import->options ?? [];
        $conversionRate = Decimal::parse($options['conversion_rate'] ?? null);
        $skipDuplicates = (bool) ($options['skip_duplicates'] ?? true);

        $errors = [];
        $buffer = [];
        $stats = ['rows' => 0, 'transactions' => 0, 'duplicates' => 0, 'errors' => 0, 'skipped' => 0, 'unconverted' => 0, 'unassigned' => 0];
        $total = '0';
        $now = now();

        $seen = [];

        $foreignCurrencies = [];

        // Lines that name their release go first, so lines carrying only an
        // ISRC can be matched to tracks created from the same file. The file
        // is read once for each group instead of being held in memory.
        foreach ([true, false] as $namesRelease) {
            foreach ($rows() as $line => $row) {
                if (($row instanceof ImportRow && ($row->upc || $row->catalogNumber || $row->releaseName)) !== $namesRelease) {
                    continue;
                }

                $stats['rows']++;

                if ($row instanceof ImportRowError) {
                    $row->skipped ? $stats['skipped']++ : $stats['errors']++;

                    if (! $row->skipped && count($errors) < self::MAX_STORED_ERRORS) {
                        $errors[] = ['line' => $line, 'message' => $row->message];
                    }

                    continue;
                }

                $timestamp = $import->replace_timestamp || $row->timestamp === null
                    ? ($import->default_timestamp?->toDateString() ?? $row->timestamp)
                    : $row->timestamp;
                $paid = $import->replace_paid_timestamp || $row->paidTimestamp === null
                    ? ($import->default_paid_timestamp?->toDateString() ?? $row->paidTimestamp)
                    : $row->paidTimestamp;
                $timestamp ??= $paid;
                $paid ??= $timestamp;

                if ($timestamp === null) {
                    $stats['errors']++;

                    if (count($errors) < self::MAX_STORED_ERRORS) {
                        $errors[] = ['line' => $line, 'message' => 'The line has no date and the import has no default date.'];
                    }

                    continue;
                }

                $currency = $row->currency ?: $label->currency;
                $rate = $currency === $label->currency ? '1' : $conversionRate;

                if ($rate !== '1' && $conversionRate !== null) {
                    $foreignCurrencies[$currency] = true;

                    if (count($foreignCurrencies) > 1) {
                        throw new ImportException('The file holds amounts in '.implode(' and ', array_keys($foreignCurrencies)).', but an import has one conversion rate. Import each currency in a file of its own.');
                    }
                }
                $format = Formats::canonical($row->format);
                $matched = $row->itemId !== null && $itemIds->has($row->itemId)
                    ? $this->matchByItem($row->itemId)
                    : $matcher->match($row, Formats::group($format));

                $payeeId = null;

                if ($row->payeeName !== null) {
                    $payeeKey = Identifiers::nameKey($row->payeeName);
                    $payeeId = $payees[$payeeKey] ??= Payee::query()->create(['label_id' => $label->id, 'name' => trim($row->payeeName)])->id;
                }

                // Identical lines are legitimate inside one statement, so the
                // fingerprint counts repeats and only detects re-imports.
                // A line without a date belongs to the period chosen at upload: the same
                // line in another period is another sale.
                $period = $row->timestamp === null && $row->paidTimestamp === null ? "|{$timestamp}|{$paid}" : '';
                $hash = sha1($import->importer.'|'.json_encode($row->raw).$period);
                $seen[$hash] = ($seen[$hash] ?? 0) + 1;

                $buffer[] = [
                    'label_id' => $label->id,
                    'data_import_id' => $import->id,
                    'release_id' => $matched['release_id'],
                    'track_id' => $matched['track_id'],
                    'item_id' => $matched['item_id'],
                    'contract_id' => $row->contractId !== null && $contractIds->has($row->contractId) ? $row->contractId : null,
                    'payee_id' => $payeeId,
                    'type' => $row->type->value,
                    'timestamp' => $timestamp,
                    'paid_timestamp' => $paid,
                    'quantity' => $row->quantity,
                    'price' => $row->price,
                    'total' => $row->total,
                    'currency' => $currency,
                    'conversion_rate' => $rate,
                    'converted_total' => $rate === null ? null : Decimal::mul($row->total, $rate),
                    'category' => $row->category,
                    'format' => $format,
                    'usage_type' => $row->usageType ? mb_substr($row->usageType, 0, 255) : null,
                    'region' => $row->region,
                    'provider' => $row->provider,
                    'retailer' => $row->retailer,
                    'external_id' => $row->externalId,
                    'source' => $row->source,
                    'importer_source' => $import->importer,
                    'memo' => $row->memo ? mb_substr($row->memo, 0, 1000) : null,
                    'published' => true,
                    'billable' => true,
                    'fingerprint' => sha1($hash.'|'.$seen[$hash]),
                    'raw_data' => json_encode($row->raw, JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($buffer) >= self::CHUNK) {
                    $this->flush($buffer, $stats, $total, $label, $skipDuplicates);
                }
            }
        }

        $this->flush($buffer, $stats, $total, $label, $skipDuplicates);

        $import->update([
            'status' => ImportStatus::Imported,
            'rows_count' => $stats['rows'],
            'transactions_count' => $stats['transactions'],
            'duplicates_count' => $stats['duplicates'],
            'errors_count' => $stats['errors'],
            'created_releases_count' => $matcher->createdReleases,
            'created_tracks_count' => $matcher->createdTracks,
            'needs_cleanup' => $stats['unassigned'] > 0 || $stats['unconverted'] > 0 || $stats['errors'] > 0,
            'total' => $total,
            'errors' => $errors,
            'metadata' => [
                'skipped' => $stats['skipped'],
                'unassigned' => $stats['unassigned'],
                'unconverted' => $stats['unconverted'],
            ],
        ]);
    }

    /**
     * Store the buffered transactions, leaving out lines that were imported
     * before, and empty the buffer.
     *
     * @param  list<array<string, mixed>>  $buffer
     * @param  array{rows: int, transactions: int, duplicates: int, errors: int, skipped: int, unconverted: int, unassigned: int}  $stats
     */
    private function flush(array &$buffer, array &$stats, string &$total, Label $label, bool $skipDuplicates): void
    {
        if ($buffer === []) {
            return;
        }

        if ($skipDuplicates) {
            $existing = DB::table('transactions')
                ->where('label_id', $label->id)
                ->whereIn('fingerprint', array_column($buffer, 'fingerprint'))
                ->pluck('fingerprint')->flip();

            $stats['duplicates'] += count(array_filter($buffer, fn (array $row): bool => $existing->has($row['fingerprint'])));
            $buffer = array_values(array_filter($buffer, fn (array $row): bool => ! $existing->has($row['fingerprint'])));
        }

        foreach ($buffer as $row) {
            $total = Decimal::add($total, $row['converted_total'] ?? '0');
            $stats['unconverted'] += $row['converted_total'] === null ? 1 : 0;
            $stats['unassigned'] += $row['release_id'] === null && $row['type'] !== TransactionType::Transfer->value ? 1 : 0;
        }

        $stats['transactions'] += count($buffer);
        DB::table('transactions')->insert($buffer);
        $buffer = [];
    }

    /**
     * @return array{release_id: int|null, track_id: int|null, item_id: int}
     */
    private function matchByItem(int $itemId): array
    {
        $item = Item::query()->findOrFail($itemId, ['id', 'release_id', 'track_id']);

        return ['release_id' => $item->release_id, 'track_id' => $item->track_id, 'item_id' => $item->id];
    }

    /**
     * Remove an import together with every transaction it created.
     */
    public function delete(DataImport $import): void
    {
        DB::transaction(function () use ($import): void {
            Transaction::query()->where('data_import_id', $import->id)->delete();
            $import->update(['status' => ImportStatus::Deleted, 'needs_cleanup' => false]);
        });
    }
}
