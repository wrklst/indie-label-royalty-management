<?php

namespace Database\Factories;

use App\Models\Label;
use App\Models\Payee;
use App\Models\Payout;
use App\Models\Period;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
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
            'payee_id' => Payee::factory(),
            'period_id' => Period::factory(),
            'total' => '10',
            'paid_on' => '2026-04-10',
        ];
    }
}
