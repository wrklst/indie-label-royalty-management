<?php

namespace Database\Factories;

use App\Enums\AirplayOrigin;
use App\Models\Airplay;
use App\Models\Label;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Airplay>
 */
class AirplayFactory extends Factory
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
            'origin' => AirplayOrigin::Manual,
            'track_name' => fake()->sentence(2),
            'artist_name' => fake()->name(),
            'station' => 'Example Radio',
        ];
    }
}
