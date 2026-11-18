<?php

namespace App\Services\Royalties\Tools;

use App\Enums\TransactionType;
use App\Models\Label;
use App\Models\Transaction;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;

/**
 * Royalties on the published price to dealer: quantity × a rate per unit.
 *
 * - The rate is a currency amount per unit, taken from the item, else the
 *   track, else the release. Having a rate is what opts a sale in.
 * - Quantities of income paid in the range are summed by release and
 *   track and multiplied by their rates.
 * - Each group is booked as two income lines that cancel out: the total
 *   on a "[PPD Income]" item and its negative on a "[PPD Income Offset]"
 *   item. A contract on the income item pays its split of the PPD total.
 *
 * @phpstan-import-type Line from ToolRuns
 *
 * @phpstan-type Row array{
 *     release_id: int,
 *     release_name: string,
 *     track_id: int|null,
 *     track_name: string|null,
 *     item_id: int|null,
 *     item_name: string|null,
 *     quantity: numeric-string,
 *     rate: numeric-string,
 *     rate_source: string,
 *     amount: numeric-string
 * }
 * @phpstan-type Report array{
 *     rows: list<Row>,
 *     groups: list<array{release_id: int, release_name: string, track_id: int|null, track_name: string|null, quantity: numeric-string, amount: numeric-string}>,
 *     total: numeric-string,
 *     quantity: numeric-string,
 *     unconverted: int,
 *     unassigned: int,
 *     without_quantity_count: int,
 *     without_quantity: list<array{id: int, paid_on: string, description: string, release_name: string|null}>
 * }
 */
class PpdTool
{
    private const string RATE = 'COALESCE(i.ppd_rate, tr.ppd_rate, r.ppd_rate)';

    /** How many transactions without a quantity are listed for fixing. */
    private const int LISTED = 50;

    public function __construct(private readonly ToolRuns $runs) {}

    /**
     * @param  array{artist_id?: int|null, release_id?: int|null, format?: string|null}  $filters
     * @return Report
     */
    public function report(Label $label, string $from, string $to, array $filters = []): array
    {
        $ignoreNegative = ToolSettings::for($label)->ignoreNegativeQuantities;
        $quantity = $ignoreNegative ? 'CASE WHEN transactions.quantity > 0 THEN transactions.quantity ELSE 0 END' : 'transactions.quantity';

        // The source decides the grouping, so an item with its own rate is
        // listed on its own and everything else by track or release.
        $source = "CASE WHEN i.ppd_rate IS NOT NULL THEN 'item' WHEN tr.ppd_rate IS NOT NULL THEN 'track' ELSE 'release' END";
        $ratedItem = 'CASE WHEN i.ppd_rate IS NOT NULL THEN i.id END';

        $found = $this->matching($label, $from, $to, $filters)
            ->whereNotNull('transactions.release_id')
            ->whereNotNull('transactions.converted_total')
            ->whereNotNull('transactions.quantity')
            ->selectRaw(
                'transactions.release_id, transactions.track_id, '.self::RATE." as rate, {$source} as rate_source, {$ratedItem} as rated_item_id, "
                ."MAX(r.name) as release_name, MAX(tr.name) as track_name, MAX(CASE WHEN i.ppd_rate IS NOT NULL THEN i.name END) as item_name, SUM({$quantity}) as quantity",
            )
            ->groupByRaw('1, 2, 3, 4, 5')
            ->orderByRaw('6, 7, 8')
            ->toBase()
            ->get();

        $rows = [];
        $groups = [];
        $total = Decimal::add('0', '0');
        $units = Decimal::add('0', '0');

        foreach ($found as $row) {
            $sold = Decimal::parse($row->quantity) ?? Decimal::add('0', '0');
            $rate = Decimal::parse($row->rate) ?? Decimal::add('0', '0');
            $amount = Decimal::mul($sold, $rate);
            $trackId = $row->track_id === null ? null : (int) $row->track_id;
            $place = [
                'release_id' => (int) $row->release_id, 'release_name' => (string) $row->release_name,
                'track_id' => $trackId, 'track_name' => $trackId === null ? null : (string) $row->track_name,
            ];

            $rows[] = [
                ...$place,
                'item_id' => $row->rated_item_id === null ? null : (int) $row->rated_item_id,
                'item_name' => $row->item_name,
                'quantity' => $sold, 'rate' => $rate, 'rate_source' => (string) $row->rate_source, 'amount' => $amount,
            ];

            $key = "{$place['release_id']}|{$trackId}";
            $groups[$key] ??= [...$place, 'quantity' => Decimal::add('0', '0'), 'amount' => Decimal::add('0', '0')];
            $groups[$key]['quantity'] = Decimal::add($groups[$key]['quantity'], $sold);
            $groups[$key]['amount'] = Decimal::add($groups[$key]['amount'], $amount);
            $total = Decimal::add($total, $amount);
            $units = Decimal::add($units, $sold);
        }

        $withoutQuantity = fn (): Builder => $this->matching($label, $from, $to, $filters)->whereNull('transactions.quantity');

        return [
            'rows' => $rows,
            'groups' => array_values($groups),
            'total' => $total,
            'quantity' => $units,
            'unconverted' => $this->matching($label, $from, $to, $filters)->whereNotNull('transactions.release_id')->whereNull('transactions.converted_total')->count(),
            'unassigned' => $this->matching($label, $from, $to, $filters)->whereNull('transactions.release_id')->count(),
            'without_quantity_count' => $withoutQuantity()->count(),
            'without_quantity' => array_values($withoutQuantity()
                ->orderBy('transactions.paid_timestamp')->orderBy('transactions.id')->limit(self::LISTED)
                ->get(['transactions.id', 'transactions.paid_timestamp', 'transactions.memo', 'transactions.source', 'transactions.retailer', 'r.name as release_name'])
                ->map(fn (Transaction $transaction): array => [
                    'id' => $transaction->id,
                    'paid_on' => $transaction->paid_timestamp->toDateString(),
                    'description' => $transaction->memo ?? $transaction->source ?? $transaction->retailer ?? "Transaction {$transaction->id}",
                    'release_name' => $transaction->getAttribute('release_name'),
                ])
                ->all()),
        ];
    }

    /**
     * @param  Report  $report
     * @return list<Line>
     */
    public function lines(array $report, string $paidOn): array
    {
        $lines = [];

        foreach ($report['groups'] as $group) {
            if (Decimal::isZero($group['amount'])) {
                continue;
            }

            $base = [
                'release_id' => $group['release_id'], 'release_name' => $group['release_name'], 'track_id' => $group['track_id'], 'track_name' => $group['track_name'],
                'type' => TransactionType::Income, 'paid_on' => $paidOn, 'category' => 'PPD',
                'memo' => 'PPD on '.MechanicalsTool::plain($group['quantity']).' units',
            ];

            $lines[] = [...$base, 'role' => 'ppd-income', 'suffix' => 'PPD Income', 'total' => $group['amount']];
            $lines[] = [...$base, 'role' => 'ppd-offset', 'suffix' => 'PPD Income Offset', 'total' => Decimal::sub('0', $group['amount'])];
        }

        return $lines;
    }

    /**
     * Income paid in the range whose item, track or release has a rate.
     *
     * @param  array{artist_id?: int|null, release_id?: int|null, format?: string|null}  $filters
     * @return Builder<Transaction>
     */
    private function matching(Label $label, string $from, string $to, array $filters): Builder
    {
        return $this->runs->sales($label, $from, $to)
            ->leftJoin('items as i', 'i.id', '=', 'transactions.item_id')
            ->leftJoin('tracks as tr', 'tr.id', '=', 'transactions.track_id')
            ->leftJoin('releases as r', 'r.id', '=', 'transactions.release_id')
            ->whereRaw(self::RATE.' IS NOT NULL')
            ->when($filters['artist_id'] ?? null, fn (Builder $query, int $artist) => $query->whereRaw('COALESCE(tr.artist_id, r.artist_id) = ?', [$artist]))
            ->when($filters['release_id'] ?? null, fn (Builder $query, int $release) => $query->where('transactions.release_id', $release))
            ->when($filters['format'] ?? null, fn (Builder $query, string $format) => $query->where('transactions.format', $format));
    }
}
