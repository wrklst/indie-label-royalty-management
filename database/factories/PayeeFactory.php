<?php

namespace Database\Factories;

use App\Models\Label;
use App\Models\Payee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payee>
 */
class PayeeFactory extends Factory
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
            'name' => fake()->unique()->name(),
        ];
    }
}
