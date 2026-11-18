<?php

namespace App\Models;

use App\Enums\RecurrenceInterval;
use App\Models\Concerns\BelongsToLabel;
use Carbon\CarbonImmutable;
use Database\Factories\RecurringExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cost the label pays at a regular interval, such as a subscription.
 * Each due date becomes an ordinary expense transaction borne by the
 * label alone.
 *
 * @property int $id
 * @property int $label_id
 * @property string $name
 * @property string|null $category
 * @property string $amount What is paid each time, as a positive number.
 * @property string $currency
 * @property string|float|int|null $conversion_rate
 * @property RecurrenceInterval $interval
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property int $occurrences_booked
 * @property string|null $memo
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label_id', 'name', 'category', 'amount', 'currency', 'conversion_rate', 'interval', 'starts_on', 'ends_on', 'occurrences_booked', 'memo'])]
class RecurringExpense extends Model
{
    /** @use HasFactory<RecurringExpenseFactory> */
    use BelongsToLabel, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:15',
            'interval' => RecurrenceInterval::class,
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'occurrences_booked' => 'integer',
        ];
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * The date of the n-th payment, counted from zero. Always measured
     * from the start date, so a cost starting on the 31st stays at the
     * end of the month instead of drifting to the 28th.
     */
    public function occurrence(int $number): CarbonImmutable
    {
        return $this->starts_on->addMonthsNoOverflow($number * $this->interval->months());
    }

    /**
     * The next date that is not booked yet, or null once the cost ended.
     */
    public function nextDueOn(): ?CarbonImmutable
    {
        $next = $this->occurrence($this->occurrences_booked);

        return $this->ends_on !== null && $next->greaterThan($this->ends_on) ? null : $next;
    }
}
