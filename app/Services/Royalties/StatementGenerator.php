<?php

namespace App\Services\Royalties;

use App\Models\Period;
use App\Models\Statement;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatementGenerator
{
    public function __construct(private readonly RoyaltyCalculator $calculator) {}

    /**
     * Freeze the current figures of a period into one statement per payee.
     *
     * A statement is a snapshot: later corrections never change it. When a
     * payee already has a statement for the period, the old one is archived
     * and the new one is stored as its revision.
     *
     * @param  list<int>|null  $payeeIds  Limit to these payees, or null for everyone with activity.
     * @return Collection<int, Statement>
     */
    public function generate(Period $period, ?array $payeeIds = null): Collection
    {
        $report = $this->calculator->forPeriod($period);

        return DB::transaction(function () use ($period, $payeeIds, $report): Collection {
            $statements = collect();

            foreach ($report['payees'] as $payee) {
                if ($payeeIds !== null && ! in_array($payee['payee_id'], $payeeIds, true)) {
                    continue;
                }

                if (! $this->hasActivity($payee['totals'])) {
                    continue;
                }

                $previous = Statement::query()
                    ->where('period_id', $period->id)
                    ->where('payee_id', $payee['payee_id'])
                    ->where('archived', false)
                    ->latest('id')
                    ->first();

                $previous?->update(['archived' => true]);

                $statements->push(Statement::query()->create([
                    'label_id' => $period->label_id,
                    'payee_id' => $payee['payee_id'],
                    'period_id' => $period->id,
                    'revision_of_id' => $previous?->id,
                    'name' => "{$payee['payee_name']} – {$period->name}",
                    'published_at' => $previous?->published_at,
                    'balance' => $payee['totals']['balance'],
                    'payable' => $payee['totals']['outstanding'],
                    'data' => [
                        'period' => $report['period'],
                        'currency' => $report['currency'],
                        'generated_at' => now()->toIso8601String(),
                        ...$payee,
                    ],
                ]));
            }

            return $statements;
        });
    }

    /**
     * @param  array<string, string>  $totals
     */
    private function hasActivity(array $totals): bool
    {
        foreach (['income', 'expense', 'payee_share', 'transfers', 'previous_balance', 'total_paid', 'balance'] as $key) {
            if (! Decimal::isZero($totals[$key])) {
                return true;
            }
        }

        return false;
    }
}
