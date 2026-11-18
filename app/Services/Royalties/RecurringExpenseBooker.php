<?php

namespace App\Services\Royalties;

use App\Enums\TransactionType;
use App\Models\Label;
use App\Models\RecurringExpense;
use App\Models\Transaction;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Turns the due dates of recurring label costs into expense transactions.
 */
class RecurringExpenseBooker
{
    /** Stops a mistyped start date from flooding the ledger in one go. */
    private const int MAX_PER_RUN = 600;

    /**
     * Book every payment that has come due up to the given day.
     *
     * Safe to run as often as you like: each due date is booked once.
     *
     * @return int The number of transactions created.
     */
    public function bookDue(Label $label, ?CarbonImmutable $until = null): int
    {
        $until ??= CarbonImmutable::today();
        $created = 0;

        $expenses = RecurringExpense::query()->withoutGlobalScope('label')->where('label_id', $label->id)->get();

        foreach ($expenses as $expense) {
            $created += DB::transaction(function () use ($expense, $label, $until): int {
                // Another process may be booking the same cost right now.
                $locked = RecurringExpense::query()->withoutGlobalScope('label')->whereKey($expense->id)->lockForUpdate()->first();

                if ($locked === null) {
                    return 0;
                }

                $booked = 0;

                while ($booked < self::MAX_PER_RUN) {
                    $due = $locked->nextDueOn();

                    if ($due === null || $due->greaterThan($until)) {
                        break;
                    }

                    $this->book($locked, $label, $due);
                    $locked->occurrences_booked++;
                    $booked++;
                }

                $locked->save();

                return $booked;
            });
        }

        return $created;
    }

    private function book(RecurringExpense $expense, Label $label, CarbonImmutable $date): void
    {
        $total = Decimal::sub('0', $expense->amount);
        $rate = $expense->currency === $label->currency ? '1' : Decimal::parse($expense->conversion_rate);

        Transaction::query()->create([
            'label_id' => $label->id,
            'recurring_expense_id' => $expense->id,
            'type' => TransactionType::Expense,
            'recoupable' => false,
            'timestamp' => $date->toDateString(),
            'paid_timestamp' => $date->toDateString(),
            'total' => $total,
            'currency' => $expense->currency,
            'conversion_rate' => $rate,
            'converted_total' => $rate === null ? null : Decimal::mul($total, $rate),
            'category' => $expense->category,
            'source' => $expense->name,
            'importer_source' => 'recurring',
            'memo' => $expense->memo ?? $expense->name,
        ]);
    }
}
