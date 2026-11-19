<?php

namespace Database\Factories;

use App\Models\Label;
use App\Models\PlatformLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformLink>
 */
class PlatformLinkFactory extends Factory
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
            'platform' => 'bandcamp',
            'url' => 'https://example.bandcamp.com/album/example-release',
            'origin' => PlatformLink::MANUAL,
        ];
    }
}
