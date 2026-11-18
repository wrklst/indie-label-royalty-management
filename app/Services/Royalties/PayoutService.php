<?php

namespace App\Services\Royalties;

use App\Models\Payee;
use App\Models\Payout;
use App\Models\Period;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayoutService
{
    public function __construct(private readonly RoyaltyCalculator $calculator) {}

    /**
     * Record a payment to a payee and book it against their ledgers,
     * largest positive balance first.
     *
     * @throws ValidationException when the amount exceeds what is payable
     */
    public function record(Period $period, Payee $payee, string $total, string $paidOn, ?string $memo = null, ?User $user = null): Payout
    {
        return DB::transaction(function () use ($period, $payee, $total, $paidOn, $memo, $user): Payout {
            // One payout per payee at a time, so two submissions cannot both pass the check below.
            Payee::query()->withoutGlobalScope('label')->whereKey($payee->id)->lockForUpdate()->first();

            $report = $this->calculator->forPeriod($period, $payee->id);
            $ledgers = collect($report['payees'][0]['ledgers'] ?? [])
                ->filter(fn (array $ledger): bool => Decimal::isPositive($ledger['balance']))
                ->sort(fn (array $a, array $b): int => Decimal::cmp($b['balance'], $a['balance']))
                ->values();

            $outstanding = $ledgers->reduce(fn (string $sum, array $ledger): string => Decimal::add($sum, $ledger['balance']), '0');
            $outstanding = Decimal::max('0', Decimal::sub($outstanding, $this->paidOnLaterPeriods($period, $payee)));

            if (Decimal::cmp($total, Decimal::add(Decimal::round($outstanding, 2), '0.005')) > 0) {
                throw ValidationException::withMessages([
                    'total' => 'The payout exceeds the '.Decimal::round($outstanding, 2).' payable to '.$payee->name.' for this period.',
                ]);
            }

            $payout = Payout::query()->create([
                'label_id' => $period->label_id,
                'payee_id' => $payee->id,
                'period_id' => $period->id,
                'user_id' => $user?->id,
                'total' => $total,
                'paid_on' => $paidOn,
                'memo' => $memo,
            ]);

            $remaining = $total;

            foreach ($ledgers as $index => $ledger) {
                if (! Decimal::isPositive($remaining)) {
                    break;
                }

                // Rounding the payable up to whole cents can leave a sliver; it lands on the last ledger.
                $amount = $index === $ledgers->count() - 1 ? $remaining : Decimal::min($remaining, $ledger['balance']);
                $payout->allocations()->create(['ledger_id' => $ledger['ledger_id'], 'amount' => $amount]);
                $remaining = Decimal::sub($remaining, $amount);
            }

            return $payout;
        });
    }

    /**
     * What the payee was already paid on later periods. Those periods
     * carry this period's balance forward, so a payout booked there has
     * settled it, although this period's own figures cannot show that.
     */
    private function paidOnLaterPeriods(Period $period, Payee $payee): string
    {
        return DB::table('payouts as p')
            ->join('periods as pp', 'pp.id', '=', 'p.period_id')
            ->where('p.label_id', $period->label_id)
            ->where('p.payee_id', $payee->id)
            ->where('pp.starts_on', '>', $period->ends_on->toDateString())
            ->pluck('p.total')
            ->reduce(fn (string $sum, mixed $paid): string => Decimal::add($sum, (string) $paid), '0');
    }
}
