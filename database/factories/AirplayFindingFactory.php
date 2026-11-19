<?php

namespace Database\Factories;

use App\Models\AirplayFinding;
use App\Models\Label;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AirplayFinding>
 */
class AirplayFindingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = fake()->unique()->url();

        return [
            'label_id' => Label::factory(),
            'track_id' => Track::factory(),
            'status' => AirplayFinding::NEW,
            'url' => $url,
            'url_hash' => sha1($url),
            'title' => fake()->sentence(3),
            'domain' => 'example.com',
            'query' => 'example query',
        ];
    }
}
