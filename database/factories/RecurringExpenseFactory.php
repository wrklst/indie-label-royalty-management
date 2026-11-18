<?php

namespace Database\Factories;

use App\Models\Label;
use App\Models\RecurringExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringExpense>
 */
class RecurringExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label_id' => Label::factory(),
            'name' => 'Example subscription',
            'category' => 'Subscriptions',
            'amount' => '10',
            'currency' => 'EUR',
            'interval' => 'monthly',
            'starts_on' => '2026-01-15',
        ];
    }
}
