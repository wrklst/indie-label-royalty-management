<?php

namespace App\Services\Reports;

use App\Models\PlayCount;
use App\Models\Transaction;
use App\Support\Platforms;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The one report every page shows for its income: how it developed
 * month by month, which platforms it came from, what they paid per
 * stream, what kind of use it was and where it was. The same figures
 * for the whole label, a release, a track, an artist, an import or a
 * period, so they can be read the same way everywhere.
 *
 * @phpstan-type Platform array{
 *     key: string, name: string, position: int, total: float, streams: float, stream_total: float,
 *     downloads: float, download_total: float, other_units: float,
 *     plays: int|null, plays_from: string|null, plays_to: string|null, reported_streams: float|null,
 *     series: array{total: array<int, float>, streams: array<int, float>, stream_total: array<int, float>},
 * }
 * @phpstan-type Mover array{id: int, label: string, total: float, streams: float, stream_total: float, recent: float, before: float, series: array<int, float>}
 */
class IncomeReport
{
    /** How many months the small trend lines cover. */
    private const int TREND = 12;

    /**
     * @param  Builder<Transaction>  $transactions  Any selection; only its converted income counts.
     * @param  Builder<PlayCount>|null  $plays  What the platforms themselves counted for the same selection.
     * @param  'timestamp'|'paid_timestamp'  $dateColumn
     * @return array{
     *     months: list<string>,
     *     total: float,
     *     platforms: list<Platform>,
     *     kinds: list<array{key: string, label: string, total: float, units: float}>,
     *     regions: list<array{label: string|null, total: float, units: float}>,
     * }
     */
    public static function of(Builder $transactions, ?Builder $plays = null, string $dateColumn = 'timestamp'): array
    {
        $income = self::income($transactions);
        $month = self::month($dateColumn);

        $rows = $income->clone()->whereNotNull($dateColumn)
            ->selectRaw("{$month} as month, retailer, format, SUM(converted_total) as total, SUM(quantity) as units")
            ->groupByRaw("{$month}, retailer, format")->get();

        $months = self::between($rows->min('month'), $rows->max('month'));
        $place = array_flip($months);
        $order = array_flip(array_keys(Platforms::names()));
        $empty = array_fill(0, count($months), 0.0);
        $platforms = [];
        $kinds = [];

        foreach ($rows as $row) {
            $platform = Platforms::of($row->retailer);
            $kind = Platforms::kind($row->format);
            $total = (float) $row->total;
            $units = (float) $row->units;
            $at = $place[$row->month];

            $entry = $platforms[$platform['key']] ?? self::platform($platform['key'], $platform['name'], $order, $empty);
            $entry['total'] = self::sum($entry['total'], $total);
            $entry['series']['total'][$at] = self::sum($entry['series']['total'][$at], $total);

            if ($kind === Platforms::STREAMS) {
                $entry['streams'] = self::sum($entry['streams'], $units);
                $entry['stream_total'] = self::sum($entry['stream_total'], $total);
                $entry['series']['streams'][$at] = self::sum($entry['series']['streams'][$at], $units);
                $entry['series']['stream_total'][$at] = self::sum($entry['series']['stream_total'][$at], $total);
            } elseif ($kind === Platforms::DOWNLOADS) {
                $entry['downloads'] = self::sum($entry['downloads'], $units);
                $entry['download_total'] = self::sum($entry['download_total'], $total);
            } else {
                $entry['other_units'] = self::sum($entry['other_units'], $units);
            }

            $platforms[$platform['key']] = $entry;

            $kinds[$kind] ??= ['key' => $kind, 'label' => Platforms::KINDS[$kind], 'total' => 0.0, 'units' => 0.0];
            $kinds[$kind]['total'] = self::sum($kinds[$kind]['total'], $total);
            $kinds[$kind]['units'] = self::sum($kinds[$kind]['units'], $units);
        }

        foreach ($plays?->clone()->toBase()->selectRaw('platform, MIN(period_start) as starts, MAX(period_end) as ends, SUM(plays) as plays')->groupBy('platform')->get() ?? [] as $counted) {
            $key = (string) $counted->platform;
            $entry = $platforms[$key] ?? self::platform($key, Platforms::names()[$key] ?? Platforms::of(null)['name'], $order, $empty);
            $from = substr((string) $counted->starts, 0, 7);
            $to = substr((string) $counted->ends, 0, 7);
            $reported = 0.0;

            // Statements are monthly, so the months the count touches are held against it.
            foreach ($months as $at => $name) {
                $reported = self::sum($reported, $name >= $from && $name <= $to ? $entry['series']['streams'][$at] : 0.0);
            }

            $entry['plays'] = (int) $counted->plays;
            $entry['plays_from'] = substr((string) $counted->starts, 0, 10);
            $entry['plays_to'] = substr((string) $counted->ends, 0, 10);
            $entry['reported_streams'] = $reported;
            $platforms[$key] = $entry;
        }

        $platforms = array_values($platforms);
        usort($platforms, fn (array $a, array $b): int => [$b['total'], $b['plays'] ?? 0] <=> [$a['total'], $a['plays'] ?? 0]);

        return [
            'months' => $months,
            'total' => round(array_sum(array_column($platforms, 'total')), 4),
            'platforms' => $platforms,
            // In the order of Platforms::KINDS, whatever the statements came in.
            'kinds' => array_values(array_filter(array_map(fn (string $kind): ?array => $kinds[$kind] ?? null, array_keys(Platforms::KINDS)))),
            'regions' => array_values($income->clone()->selectRaw('region as label, SUM(converted_total) as total, SUM(quantity) as units')
                ->groupBy('region')->orderByDesc('total')->limit(12)->get()
                ->map(fn ($row): array => ['label' => $row->label === null ? null : (string) $row->label, 'total' => round((float) $row->total, 4), 'units' => round((float) $row->units, 4)])
                ->all()),
        ];
    }

    /**
     * The releases or tracks of a selection that earned most, each with
     * its last twelve months, to see what is still moving.
     *
     * @param  Builder<Transaction>  $transactions
     * @param  'release_id'|'track_id'  $column
     * @param  'timestamp'|'paid_timestamp'  $dateColumn
     * @return array{months: list<string>, rows: list<Mover>}
     */
    public static function movers(Builder $transactions, string $column, int $limit = 12, string $dateColumn = 'timestamp'): array
    {
        $income = self::income($transactions)->whereNotNull($column)->whereNotNull($dateColumn);
        $month = self::month($dateColumn);
        $top = $income->clone()->selectRaw("{$column} as id, SUM(converted_total) as total")->groupBy($column)->orderByDesc('total')->limit($limit)->pluck('total', 'id');
        $last = $income->clone()->max($dateColumn);

        if ($top->isEmpty() || $last === null) {
            return ['months' => [], 'rows' => []];
        }

        $end = CarbonImmutable::parse($last)->startOfMonth();
        $months = self::between($end->subMonths(self::TREND - 1)->format('Y-m'), $end->format('Y-m'));
        $place = array_flip($months);
        $names = DB::table($column === 'release_id' ? 'releases' : 'tracks')->whereIn('id', $top->keys())->pluck('name', 'id');
        $found = [];

        foreach ($top as $id => $total) {
            $found[(int) $id] = [
                'id' => (int) $id, 'label' => (string) ($names[$id] ?? ''), 'total' => round((float) $total, 4),
                'streams' => 0.0, 'stream_total' => 0.0, 'recent' => 0.0, 'before' => 0.0, 'series' => array_fill(0, self::TREND, 0.0),
            ];
        }

        $rows = $income->clone()->whereIn($column, $top->keys())
            ->selectRaw("{$column} as id, {$month} as month, format, SUM(converted_total) as total, SUM(quantity) as units")
            ->groupByRaw("{$column}, {$month}, format")->get();

        foreach ($rows as $row) {
            $entry = $found[(int) $row->id] ?? null;

            if ($entry === null) {
                continue;
            }

            if (Platforms::kind($row->format) === Platforms::STREAMS) {
                $entry['streams'] = self::sum($entry['streams'], (float) $row->units);
                $entry['stream_total'] = self::sum($entry['stream_total'], (float) $row->total);
            }

            $at = $place[$row->month] ?? null;

            if ($at !== null) {
                $entry['series'][$at] = self::sum($entry['series'][$at], (float) $row->total);

                // The last three months against the three before them.
                if ($at >= self::TREND - 3) {
                    $entry['recent'] = self::sum($entry['recent'], (float) $row->total);
                } elseif ($at >= self::TREND - 6) {
                    $entry['before'] = self::sum($entry['before'], (float) $row->total);
                }
            }

            $found[(int) $row->id] = $entry;
        }

        return ['months' => $months, 'rows' => array_values($found)];
    }

    /**
     * @param  Builder<Transaction>  $transactions
     */
    private static function income(Builder $transactions): BaseBuilder
    {
        // Lines not converted to the label's currency yet count nowhere.
        return $transactions->clone()->where('type', 'income')->whereNotNull('converted_total')->toBase();
    }

    /**
     * @param  array<string, int>  $order
     * @param  array<int, float>  $empty
     * @return Platform
     */
    private static function platform(string $key, string $name, array $order, array $empty): array
    {
        return [
            'key' => $key, 'name' => $name,
            // Its place decides its colour: fixed for known platforms, and always the same for any other name.
            'position' => $order[$key] ?? count($order) + crc32($key) % 24,
            'total' => 0.0, 'streams' => 0.0, 'stream_total' => 0.0, 'downloads' => 0.0, 'download_total' => 0.0, 'other_units' => 0.0,
            'plays' => null, 'plays_from' => null, 'plays_to' => null, 'reported_streams' => null,
            'series' => ['total' => $empty, 'streams' => $empty, 'stream_total' => $empty],
        ];
    }

    /**
     * Added up without the noise floats gather: 0.1 + 0.2 is 0.3 here.
     */
    private static function sum(float $a, float $b): float
    {
        return round($a + $b, 4);
    }

    /**
     * Every month from one to another, as "2026-03".
     *
     * @return list<string>
     */
    private static function between(?string $first, ?string $last): array
    {
        if ($first === null || $last === null) {
            return [];
        }

        $months = [];

        for ($month = CarbonImmutable::parse($first.'-01'); $month->format('Y-m') <= $last; $month = $month->addMonth()) {
            $months[] = $month->format('Y-m');
        }

        return $months;
    }

    /**
     * @param  literal-string  $column
     * @return literal-string
     */
    private static function month(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', {$column})" : "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
