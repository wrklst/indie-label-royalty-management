<?php

namespace App\Services\Bandcamp;

use App\Enums\ImportStatus;
use App\Models\BandcampConnection;
use App\Models\DataImport;
use App\Models\Label;
use App\Services\Imports\ImportRunner;
use App\Services\Imports\Row;
use App\Support\CurrentLabel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fetches a label's Bandcamp sales and imports them like an uploaded
 * sales report.
 *
 * Sales are fetched month by month from where the last run stopped. Each
 * month with new sales becomes one data import, so it shows up, can be
 * inspected and can be deleted like any other statement.
 */
class BandcampSync
{
    /**
     * The columns written to the import file, in this order. Everything
     * else Bandcamp sends, most of all the buyer's name, email, phone,
     * notes, address and city, never reaches the disk.
     *
     * The order and the formatting of the values must stay as they are:
     * the import fingerprint is built from the stored line.
     *
     * @var list<string>
     */
    public const array COLUMNS = [
        'bandcamp_transaction_id', 'bandcamp_transaction_item_id', 'bandcamp_related_transaction_id', 'date',
        'item_type', 'item_name', 'artist', 'currency', 'item_price', 'quantity', 'discount_code', 'sub_total',
        'additional_fan_contribution', 'seller_tax', 'marketplace_tax', 'tax_rate', 'collection_society_share',
        'shipping', 'ship_from_country_name', 'transaction_fee', 'fee_type', 'item_total', 'amount_you_received',
        'net_amount', 'package', 'option', 'item_url', 'catalog_number', 'upc', 'isrc', 'sku', 'ship_date',
        'country', 'country_code', 'referer',
    ];

    private const string IMPORTER = 'bandcamp';

    public function __construct(
        private readonly ImportRunner $runner,
        private readonly CurrentLabel $currentLabel,
    ) {}

    /**
     * Import every sale since the last run. Stops at the first month that
     * fails; months before it stay imported and the next run resumes there.
     *
     * @param  int|null  $timeBudget  Seconds after which no further month is started, for runs inside a web request.
     *
     * @throws BandcampException
     */
    public function run(BandcampConnection $connection, ?int $userId = null, ?int $timeBudget = null): SyncResult
    {
        $lock = Cache::lock("bandcamp-sync:{$connection->id}", 900);

        if (! $lock->get()) {
            throw new BandcampException('A Bandcamp sync for this label is already running.');
        }

        try {
            $result = $this->sync($connection, $userId, $timeBudget);

            $connection->update(['last_sync_at' => now(), 'last_sync_status' => 'ok', 'last_sync_message' => $result->summary()]);

            return $result;
        } catch (Throwable $exception) {
            $connection->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'failed',
                'last_sync_message' => $exception instanceof BandcampException ? $exception->getMessage() : 'The sync stopped unexpectedly.',
            ]);

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function sync(BandcampConnection $connection, ?int $userId, ?int $timeBudget): SyncResult
    {
        $startedAt = microtime(true);
        $bandId = $connection->band_id;
        $from = $connection->last_synced_until ?? $connection->sync_from?->startOfDay();

        if ($bandId === null || $from === null) {
            throw new BandcampException('Choose the Bandcamp account and the date to start from before syncing.');
        }

        $label = Label::query()->findOrFail($connection->label_id);

        if ($this->currentLabel->id() !== $label->id) {
            $this->currentLabel->set($label);
        }

        $client = new BandcampClient($connection);
        $from = $from->utc();
        $until = CarbonImmutable::now('UTC')->startOfSecond();
        $stats = ['windows' => 0, 'imports' => 0, 'transactions' => 0, 'known' => 0, 'errors' => 0];

        while ($from < $until) {
            if ($timeBudget !== null && $stats['windows'] > 0 && microtime(true) - $startedAt >= $timeBudget) {
                break;
            }

            $to = $from->addMonthNoOverflow()->min($until);
            $lines = array_map($this->line(...), $client->salesReport($bandId, $from, $to, $connection->member_band_id));
            $lines = array_values(array_filter($lines, fn (array $line): bool => mb_strtolower($line['item_type']) !== 'payout'));
            $new = $this->withoutImported($label, $lines);
            $stats['windows']++;
            $stats['known'] += count($lines) - count($new);

            if ($new !== []) {
                $import = $this->import($label, $new, $from, $to, $userId);

                if ($import->status !== ImportStatus::Imported) {
                    throw new BandcampException("Sales from {$from->toDateString()} to {$to->toDateString()} could not be imported: ".($import->errors[0]['message'] ?? 'the import failed.'));
                }

                $stats['imports']++;
                $stats['transactions'] += $import->transactions_count;
                $stats['known'] += $import->duplicates_count;
                $stats['errors'] += $import->errors_count;
            }

            $connection->update(['last_synced_until' => $to]);
            $from = $to;
        }

        return new SyncResult($stats['windows'], $stats['imports'], $stats['transactions'], $stats['known'], $stats['errors'], $from, $from >= $until);
    }

    /**
     * Reduce an API line item to the stored columns, as text. The same
     * item always produces the same line, whenever it is fetched.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    private function line(array $item): array
    {
        $line = [];

        foreach (self::COLUMNS as $column) {
            $value = $item[$column] ?? null;

            $line[$column] = match (true) {
                is_string($value) => trim($value),
                is_int($value) => (string) $value,
                is_float($value) => (string) json_encode($value),
                default => '',
            };
        }

        return $line;
    }

    /**
     * Leave out the lines whose sale is in the ledger already, whether it
     * came from an earlier sync or from a sales report someone uploaded.
     *
     * A synced sale is recognised by Bandcamp's order id together with its
     * id of the item within the order. Downloaded reports do not carry that id, so their lines are
     * matched by order id, item type and item name, one stored line
     * accounting for one fetched line.
     *
     * @param  list<array<string, string>>  $lines
     * @return list<array<string, string>>
     */
    private function withoutImported(Label $label, array $lines): array
    {
        $itemIds = [];
        $unidentified = [];
        $orderIds = array_values(array_unique(array_filter(array_column($lines, 'bandcamp_transaction_id'), fn (string $id): bool => $id !== '')));

        foreach (array_chunk($orderIds, 500) as $chunk) {
            $stored = DB::table('transactions')
                ->where('label_id', $label->id)
                ->where('importer_source', self::IMPORTER)
                ->whereIn('external_id', $chunk)
                ->get(['external_id', 'raw_data']);

            foreach ($stored as $transaction) {
                $raw = [];

                foreach ((array) json_decode((string) $transaction->raw_data, true) as $column => $value) {
                    $raw[Row::normalize((string) $column)] = is_scalar($value) ? (string) $value : '';
                }

                $itemId = $raw['bandcamp transaction item id'] ?? '';

                if ($itemId !== '') {
                    $itemIds["{$transaction->external_id}:{$itemId}"] = true;
                } else {
                    $key = $this->saleKey((string) $transaction->external_id, $raw['item type'] ?? '', $raw['item name'] ?? '');
                    $unidentified[$key] = ($unidentified[$key] ?? 0) + 1;
                }
            }
        }

        return array_values(array_filter($lines, function (array $line) use ($itemIds, &$unidentified): bool {
            if ($line['bandcamp_transaction_item_id'] !== '' && isset($itemIds["{$line['bandcamp_transaction_id']}:{$line['bandcamp_transaction_item_id']}"])) {
                return false;
            }

            $key = $this->saleKey($line['bandcamp_transaction_id'], $line['item_type'], $line['item_name']);

            if (($unidentified[$key] ?? 0) > 0) {
                $unidentified[$key]--;

                return false;
            }

            return true;
        }));
    }

    private function saleKey(string $orderId, string $itemType, string $itemName): string
    {
        return $orderId.'|'.mb_strtolower(trim($itemType)).'|'.mb_strtolower(trim($itemName));
    }

    /**
     * Store the lines as a sales report file and run it through the
     * Bandcamp importer.
     *
     * @param  list<array<string, string>>  $lines
     */
    private function import(Label $label, array $lines, CarbonImmutable $from, CarbonImmutable $to, ?int $userId): DataImport
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new BandcampException('The sales could not be prepared for import.');
        }

        fputcsv($stream, self::COLUMNS, ',', '"', '');

        foreach ($lines as $line) {
            fputcsv($stream, array_values($line), ',', '"', '');
        }

        rewind($stream);
        $contents = (string) stream_get_contents($stream);
        fclose($stream);

        $filename = "bandcamp-sync-{$from->format('Ymd-His')}-{$to->format('Ymd-His')}.csv";
        $path = "imports/{$label->id}/".Str::random(40).'.csv';
        Storage::disk('local')->put($path, $contents);

        return $this->runner->run(DataImport::query()->create([
            'label_id' => $label->id,
            'user_id' => $userId,
            'importer' => self::IMPORTER,
            'name' => "Bandcamp sync {$from->toDateString()} – {$to->toDateString()}",
            'status' => ImportStatus::Pending,
            'original_filename' => $filename,
            'file_path' => $path,
            'file_hash' => hash('sha256', $contents),
            'options' => ['skip_duplicates' => true],
        ]), $label);
    }
}
