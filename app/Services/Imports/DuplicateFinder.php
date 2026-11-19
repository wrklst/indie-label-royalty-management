<?php

namespace App\Services\Imports;

use App\Models\DataImport;
use App\Support\Decimal;
use App\Support\Formats;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Finds lines of an import that look like bookings another source already
 * made: a manual entry, a migrated statement, another importer. Lines of
 * the same importer are told apart by their fingerprint before they get
 * here, so only bookings of other sources are compared.
 */
class DuplicateFinder
{
    public const string REPLACE = 'replace';

    public const string SKIP = 'skip';

    public const string BOTH = 'both';

    /** Manual entries are rounded to cents; a sum may be off by half a cent per booking. */
    private const float ROUNDING = 0.005;

    /**
     * Compare the lines an import just stored with the bookings that were there before.
     *
     * Two things count as a possible duplicate. The lines of one release,
     * type, kind of format and paid month add up to what was booked there
     * before: a statement entered as one sum per release. Or one line has
     * the paid date and amount of one earlier booking of that release.
     *
     * @return list<array{
     *     key: string,
     *     kind: 'sum'|'line',
     *     release: string|null,
     *     existing: list<array{id: int, paid: string, total: string, memo: string|null, source: string|null}>,
     *     existing_total: string,
     *     imported: array{count: int, total: string, tracks: list<string>},
     *     imported_ids: list<int>,
     * }>
     */
    public function find(DataImport $import): array
    {
        $columns = ['id', 'release_id', 'track_id', 'type', 'format', 'paid_timestamp', 'total', 'converted_total', 'memo', 'importer_source', 'source'];
        $imported = DB::table('transactions')->where('data_import_id', $import->id)->whereNotNull('release_id')->get($columns);

        if ($imported->isEmpty()) {
            return [];
        }

        $existing = DB::table('transactions')
            ->where('label_id', $import->label_id)
            ->whereIn('release_id', $imported->pluck('release_id')->unique())
            ->whereBetween('paid_timestamp', [
                CarbonImmutable::parse($imported->min('paid_timestamp'))->startOfMonth()->toDateString(),
                CarbonImmutable::parse($imported->max('paid_timestamp'))->endOfMonth()->toDateString(),
            ])
            ->where(fn ($query) => $query->whereNull('data_import_id')->orWhere('data_import_id', '!=', $import->id))
            ->where(fn ($query) => $query->whereNull('importer_source')->orWhere('importer_source', '!=', $import->importer))
            ->orderBy('id')
            ->get($columns);

        if ($existing->isEmpty()) {
            return [];
        }

        $bucket = fn (stdClass $row): string => implode('|', [$row->release_id, $row->type, Formats::group($row->format) ?? '', substr((string) $row->paid_timestamp, 0, 7)]);
        $lines = $imported->groupBy($bucket);
        $proposals = [];

        foreach ($existing->groupBy($bucket) as $key => $bookings) {
            $candidates = $lines->get($key);

            if ($candidates === null) {
                continue;
            }

            // ponytail: floats are exact enough to compare sums to the cent; nothing is booked from them.
            if (abs($this->sum($bookings) - $this->sum($candidates)) <= self::ROUNDING * ($bookings->count() + 1)) {
                $proposals[] = $this->proposal('sum', $bookings, $candidates);

                continue;
            }

            foreach ($bookings as $booking) {
                $match = $candidates->first(fn (stdClass $line): bool => substr((string) $line->paid_timestamp, 0, 10) === substr((string) $booking->paid_timestamp, 0, 10)
                    && round($this->amount($line), 2) === round($this->amount($booking), 2));

                if ($match !== null) {
                    $candidates = $candidates->reject(fn (stdClass $line): bool => $line->id === $match->id);
                    $proposals[] = $this->proposal('line', collect([$booking]), collect([$match]));
                }
            }
        }

        return $proposals;
    }

    /**
     * Carry out what was decided for each proposal.
     *
     * "replace" keeps the imported lines and removes the earlier bookings;
     * the lines stay unpublished when a booking was, and take over a
     * contract, payee or ledger every booking shared. "skip" leaves the
     * imported lines out. "both" keeps everything.
     *
     * @param  list<array<string, mixed>>  $proposals  As returned by find() in this run.
     * @param  array<string, string>  $decisions  Decision per proposal key.
     * @return list<array<string, mixed>> What was decided, for the import's record.
     */
    public function apply(array $proposals, array $decisions): array
    {
        $record = [];

        foreach ($proposals as $proposal) {
            $decision = $decisions[$proposal['key']];
            $existingIds = array_column($proposal['existing'], 'id');

            if ($decision === self::SKIP) {
                DB::table('transactions')->whereIn('id', $proposal['imported_ids'])->delete();
            }

            if ($decision === self::REPLACE) {
                $bookings = DB::table('transactions')->whereIn('id', $existingIds)->get(['published', 'contract_id', 'payee_id', 'ledger_id']);
                $carried = $bookings->contains(fn (stdClass $booking): bool => ! $booking->published) ? ['published' => false] : [];

                if ($carried !== []) {
                    DB::table('transactions')->whereIn('id', $proposal['imported_ids'])->update($carried);
                }

                foreach (['contract_id', 'payee_id', 'ledger_id'] as $column) {
                    $shared = $bookings->pluck($column)->unique();

                    if ($shared->count() === 1 && $shared->first() !== null) {
                        DB::table('transactions')->whereIn('id', $proposal['imported_ids'])->whereNull($column)->update([$column => $shared->first()]);
                    }
                }

                DB::table('transactions')->whereIn('id', $existingIds)->delete();
            }

            $record[] = [...collect($proposal)->except('imported_ids')->all(), 'decision' => $decision];
        }

        return $record;
    }

    /**
     * @param  'sum'|'line'  $kind
     * @param  Collection<int, stdClass>  $bookings
     * @param  Collection<int, stdClass>  $lines
     * @return array{
     *     key: string,
     *     kind: 'sum'|'line',
     *     release: string|null,
     *     existing: list<array{id: int, paid: string, total: string, memo: string|null, source: string|null}>,
     *     existing_total: string,
     *     imported: array{count: int, total: string, tracks: list<string>},
     *     imported_ids: list<int>,
     * }
     */
    private function proposal(string $kind, Collection $bookings, Collection $lines): array
    {
        $release = DB::table('releases')->where('id', $bookings->first()?->release_id)->value('name');
        $text = fn (mixed $value): ?string => $value === null ? null : (string) $value;

        return [
            'key' => substr(sha1($kind.'|'.$bookings->pluck('id')->implode(',')), 0, 16),
            'kind' => $kind,
            'release' => $text($release),
            'existing' => array_values($bookings->map(fn (stdClass $booking): array => [
                'id' => (int) $booking->id,
                'paid' => substr((string) $booking->paid_timestamp, 0, 10),
                'total' => Decimal::round((string) ($booking->converted_total ?? $booking->total), 2),
                'memo' => $text($booking->memo),
                'source' => $text($booking->importer_source ?? $booking->source),
            ])->all()),
            'existing_total' => Decimal::round((string) $this->sum($bookings), 2),
            'imported' => [
                'count' => $lines->count(),
                'total' => Decimal::round((string) $this->sum($lines), 2),
                'tracks' => array_values(array_map(strval(...), DB::table('tracks')->whereIn('id', $lines->pluck('track_id')->filter()->unique()->take(5))->orderBy('name')->pluck('name')->all())),
            ],
            'imported_ids' => array_values($lines->map(fn (stdClass $line): int => (int) $line->id)->all()),
        ];
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     */
    private function sum(Collection $rows): float
    {
        return (float) $rows->sum(fn (stdClass $row): float => $this->amount($row));
    }

    private function amount(stdClass $row): float
    {
        return (float) ($row->converted_total ?? $row->total);
    }
}
