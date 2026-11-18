<?php

namespace App\Services\Royalties;

use App\Models\Period;
use App\Models\Statement;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatementGenerator
{
    public function __construct(private readonly RoyaltyCalculator $calculator) {}

    /**
     * Freeze the current figures of a period into one statement per payee.
     *
     * A statement is a snapshot: later corrections never change it. When a
     * payee already has a statement for the period, the new one is stored
     * as its revision and always starts as a draft. A draft it replaces is
     * archived; a published one stays visible to the payee until the
     * revision is published.
     *
     * @param  list<int>|null  $payeeIds  Limit to these payees, or null for everyone with activity.
     * @return Collection<int, Statement>
     */
    public function generate(Period $period, ?array $payeeIds = null): Collection
    {
        $report = $this->calculator->forPeriod($period);

        return DB::transaction(function () use ($period, $payeeIds, $report): Collection {
            $statements = new EloquentCollection;

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

                // A draft is replaced outright. A published statement stays
                // with the payee until its revision has been reviewed and published.
                if ($previous?->published_at === null) {
                    $previous?->update(['archived' => true]);
                }

                $statements->push(Statement::query()->create([
                    'label_id' => $period->label_id,
                    'payee_id' => $payee['payee_id'],
                    'period_id' => $period->id,
                    'revision_of_id' => $previous?->id,
                    'name' => "{$payee['payee_name']} – {$period->name}",
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

            // A draft of a payee who no longer has anything to state would otherwise stay publishable.
            Statement::query()
                ->where('period_id', $period->id)
                ->where('archived', false)
                ->whereNull('published_at')
                ->whereNotIn('id', $statements->modelKeys())
                ->when($payeeIds !== null, fn ($query) => $query->whereIn('payee_id', $payeeIds))
                ->update(['archived' => true]);

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
