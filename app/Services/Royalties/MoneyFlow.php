<?php

namespace App\Services\Royalties;

use App\Models\Transaction;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where the money of a set of income went before it reached the label:
 * what customers paid, what the distributor or shop kept, what the
 * payment provider kept, and what arrived.
 *
 * Only statements that say so can be broken down. Income from the others
 * is reported as received, with nothing known about what came before.
 */
class MoneyFlow
{
    /**
     * @param  Builder<Transaction>  $transactions  Any selection; only its income counts.
     * @return array{
     *     gross: numeric-string,
     *     distribution_fee: numeric-string,
     *     payment_fee: numeric-string,
     *     other_deductions: numeric-string,
     *     received_with_breakdown: numeric-string,
     *     received_without_breakdown: numeric-string,
     *     received: numeric-string,
     *     lines_with_breakdown: int,
     *     lines_without_breakdown: int,
     * }
     */
    public static function of(Builder $transactions): array
    {
        // Amounts are in the currency of the transaction; lines not converted yet count nowhere.
        $row = $transactions->clone()->where('type', 'income')->whereNotNull('converted_total')->toBase()->selectRaw('
            SUM(CASE WHEN gross_total IS NOT NULL THEN gross_total * conversion_rate ELSE 0 END) as gross,
            SUM(CASE WHEN gross_total IS NOT NULL THEN COALESCE(distribution_fee, 0) * conversion_rate ELSE 0 END) as distribution_fee,
            SUM(CASE WHEN gross_total IS NOT NULL THEN COALESCE(payment_fee, 0) * conversion_rate ELSE 0 END) as payment_fee,
            SUM(CASE WHEN gross_total IS NOT NULL THEN converted_total ELSE 0 END) as received_with_breakdown,
            SUM(CASE WHEN gross_total IS NULL THEN converted_total ELSE 0 END) as received_without_breakdown,
            SUM(CASE WHEN gross_total IS NOT NULL THEN 1 ELSE 0 END) as lines_with_breakdown,
            SUM(CASE WHEN gross_total IS NULL THEN 1 ELSE 0 END) as lines_without_breakdown
        ')->first();

        $sum = fn (string $key): string => Decimal::parse($row->{$key} ?? 0) ?? '0';
        $with = $sum('received_with_breakdown');
        $without = $sum('received_without_breakdown');

        return [
            'gross' => $sum('gross'),
            'distribution_fee' => $sum('distribution_fee'),
            'payment_fee' => $sum('payment_fee'),
            // Taxes, shipping, mechanicals passed on: whatever else separates gross from what arrived.
            'other_deductions' => Decimal::sub(Decimal::sub(Decimal::sub($sum('gross'), $sum('distribution_fee')), $sum('payment_fee')), $with),
            'received_with_breakdown' => $with,
            'received_without_breakdown' => $without,
            'received' => Decimal::add($with, $without),
            'lines_with_breakdown' => (int) ($row->lines_with_breakdown ?? 0),
            'lines_without_breakdown' => (int) ($row->lines_without_breakdown ?? 0),
        ];
    }
}
