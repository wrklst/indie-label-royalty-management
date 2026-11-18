<?php

namespace Database\Factories;

use App\Models\Label;
use App\Models\Release;
use App\Models\ReleaseFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReleaseFile>
 */
class ReleaseFileFactory extends Factory
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
            'kind' => 'document',
            'disk' => 'local',
            'path' => 'releases/'.fake()->uuid().'.pdf',
            'original_name' => 'press-kit.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ];
    }
}
