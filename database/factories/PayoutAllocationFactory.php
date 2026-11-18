<?php

namespace Database\Factories;

use App\Models\Payout;
use App\Models\PayoutAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayoutAllocation>
 */
class PayoutAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payout_id' => Payout::factory(),
            'amount' => '10',
        ];
    }
}
