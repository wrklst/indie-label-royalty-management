<?php

namespace Database\Factories;

use App\Models\Label;
use App\Models\PlayCount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlayCount>
 */
class PlayCountFactory extends Factory
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
            'batch' => (string) Str::ulid(),
            'source_file' => 'example-plays.csv',
            'platform' => 'spotify',
            'track_name' => 'Example Track',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'plays' => 100,
        ];
    }
}
