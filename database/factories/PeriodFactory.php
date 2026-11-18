<?php

namespace Database\Factories;

use App\Models\Label;
use App\Models\Period;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Period>
 */
class PeriodFactory extends Factory
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
            'name' => '2026 Q1',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-03-31',
        ];
    }
}
