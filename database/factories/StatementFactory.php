<?php

namespace Database\Factories;

use App\Models\Label;
use App\Models\Payee;
use App\Models\Period;
use App\Models\Statement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Statement>
 */
class StatementFactory extends Factory
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
            'name' => fake()->sentence(3),
            'data' => [],
        ];
    }
}
