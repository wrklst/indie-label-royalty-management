<?php

namespace Database\Factories;

use App\Models\Label;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
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
            'type' => 'income',
            'timestamp' => '2026-01-15',
            'paid_timestamp' => '2026-01-15',
            'quantity' => 1,
            'total' => '10',
            'currency' => 'EUR',
            'conversion_rate' => 1,
            'converted_total' => fn (array $attributes) => $attributes['total'],
            'category' => 'Distribution',
            'recoupable' => true,
        ];
    }

    /**
     * An expense the label bears alone, such as a one-time fee.
     */
    public function labelCost(): static
    {
        return $this->state(fn (): array => ['type' => 'expense', 'recoupable' => false, 'category' => 'Fee']);
    }
}
