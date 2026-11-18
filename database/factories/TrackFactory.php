<?php

namespace Database\Factories;

use App\Models\Label;
use App\Models\Release;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Track>
 */
class TrackFactory extends Factory
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
            'release_id' => Release::factory(),
            'name' => fake()->sentence(2),
            'isrc' => fake()->unique()->bothify('DE???#######'),
        ];
    }
}
