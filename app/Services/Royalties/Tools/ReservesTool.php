<?php

namespace App\Services\Royalties\Tools;

use App\Enums\TransactionType;
use App\Models\Label;
use App\Models\Release;
use App\Models\Transaction;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

/**
 * Holds back part of physical income against returns.
 *
 * - Reserve = physical income of a release paid in the range × its
 *   reserves rate. The rate is stored as a percentage: 25 means 25%.
 * - A run books two income lines per release on its "[Reserves]" item:
 *   the reserve as a negative amount on the day it is held and the same
 *   amount back on the day it is liquidated. The release earns less in
 *   the period of the first date and gets it back in that of the second.
 *
 * @phpstan-import-type Line from ToolRuns
 *
 * @phpstan-type Row array{
 *     release_id: int,
 *     release_name: string,
 *     base: numeric-string,
 *     rate: numeric-string,
 *     amount: numeric-string,
 *     held: bool
 * }
 * @phpstan-type Report array{rows: list<Row>, total: numeric-string, base: numeric-string, unconverted: int}
 */
class ReservesTool
{
    public const string HELD = 'Reserves (Held)';

    public const string LIQUIDATED = 'Reserves (Liquidated)';

    public function __construct(private readonly ToolRuns $runs) {}

    /**
     * @return Report
     */
    public function report(Label $label, string $from, string $to): array
    {
        $physical = fn () => $this->runs->sales($label, $from, $to)
            ->whereIn('format', Config::array('royalties.formats.Physical'))
            ->whereNotNull('release_id');

        // Returns are negative lines and lower what there is to hold.
        $income = $physical()->whereNotNull('converted_total')
            ->selectRaw('release_id, SUM(converted_total) as base')
            ->groupBy('release_id')
            ->toBase()
            ->pluck('base', 'release_id');

        $releases = Release::query()->withoutGlobalScope('label')
            ->where('label_id', $label->id)
            ->where('reserves_rate', '>', 0)
            ->whereKey($income->keys()->all())
            ->orderBy('name')
            ->get(['id', 'name', 'reserves_rate']);

        $rows = [];
        $total = Decimal::add('0', '0');
        $baseTotal = Decimal::add('0', '0');

        foreach ($releases as $release) {
            $base = Decimal::parse($income[$release->id]) ?? Decimal::add('0', '0');
            $rate = Decimal::parse($release->reserves_rate) ?? Decimal::add('0', '0');
            $amount = Decimal::div(Decimal::mul($base, $rate), '100');

            if (Decimal::isZero($base)) {
                continue;
            }

            // Nothing is held when returns outweighed sales in the range.
            $held = Decimal::isPositive($amount);
            $rows[] = ['release_id' => $release->id, 'release_name' => $release->name, 'base' => $base, 'rate' => $rate, 'amount' => $amount, 'held' => $held];

            if ($held) {
                $total = Decimal::add($total, $amount);
                $baseTotal = Decimal::add($baseTotal, $base);
            }
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'base' => $baseTotal,
            'unconverted' => $physical()->whereNull('converted_total')->whereIn('release_id', Release::query()->withoutGlobalScope('label')
                ->where('label_id', $label->id)->where('reserves_rate', '>', 0)->select('id'))->count(),
        ];
    }

    /**
     * @param  Report  $report
     * @return list<Line>
     */
    public function lines(array $report, string $heldOn, string $liquidatedOn): array
    {
        $lines = [];

        foreach ($report['rows'] as $row) {
            if (! $row['held']) {
                continue;
            }

            $base = [
                'release_id' => $row['release_id'], 'release_name' => $row['release_name'], 'track_id' => null, 'track_name' => null,
                'role' => 'reserves', 'suffix' => 'Reserves', 'type' => TransactionType::Income, 'category' => 'Reserves',
            ];
            $share = MechanicalsTool::plain($row['rate']).'% of '.MechanicalsTool::plain($row['base']);

            $lines[] = [...$base, 'total' => Decimal::sub('0', $row['amount']), 'paid_on' => $heldOn, 'format' => self::HELD, 'memo' => "Reserve held: {$share}"];
            $lines[] = [...$base, 'total' => $row['amount'], 'paid_on' => $liquidatedOn, 'format' => self::LIQUIDATED, 'memo' => "Reserve liquidated: {$share}"];
        }

        return $lines;
    }

    /**
     * What is held for each release right now: everything ever held,
     * less what has been liquidated up to the given day.
     *
     * @return list<array{release_id: int, release_name: string, held: numeric-string, liquidated: numeric-string, balance: numeric-string}>
     */
    public function balances(Label $label, ?CarbonImmutable $on = null): array
    {
        $today = ($on ?? CarbonImmutable::today())->toDateString();

        $rows = Transaction::query()->withoutGlobalScope('label')
            ->where('transactions.label_id', $label->id)
            ->where('transactions.type', TransactionType::Income)
            ->whereIn('transactions.format', [self::HELD, self::LIQUIDATED])
            ->whereNotNull('transactions.converted_total')
            ->join('releases as r', 'r.id', '=', 'transactions.release_id')
            ->selectRaw(
                'transactions.release_id, MAX(r.name) as release_name, '
                .'SUM(CASE WHEN transactions.format = ? THEN transactions.converted_total ELSE 0 END) as held, '
                .'SUM(CASE WHEN transactions.format = ? AND transactions.paid_timestamp <= ? THEN transactions.converted_total ELSE 0 END) as liquidated',
                [self::HELD, self::LIQUIDATED, $today],
            )
            ->groupBy('transactions.release_id')
            ->orderByRaw('2')
            ->toBase()
            ->get();

        $balances = [];

        foreach ($rows as $row) {
            // Held lines are stored negative; show what is held as a positive amount.
            $held = Decimal::sub('0', Decimal::parse($row->held));
            $liquidated = Decimal::parse($row->liquidated) ?? Decimal::add('0', '0');

            $balances[] = [
                'release_id' => (int) $row->release_id,
                'release_name' => (string) $row->release_name,
                'held' => $held,
                'liquidated' => $liquidated,
                'balance' => Decimal::sub($held, $liquidated),
            ];
        }

        return $balances;
    }
}
