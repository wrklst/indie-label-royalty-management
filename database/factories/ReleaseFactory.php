<?php

namespace Database\Factories;

use App\Models\Label;
use App\Models\Release;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Release>
 */
class ReleaseFactory extends Factory
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
            'name' => fake()->unique()->sentence(2),
            'catalog_number' => fake()->unique()->bothify('CAT###'),
            'upc' => fake()->unique()->ean13(),
        ];
    }
}
