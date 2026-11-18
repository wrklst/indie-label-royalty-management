<?php

namespace Database\Factories;

use App\Models\DataImport;
use App\Models\Label;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataImport>
 */
class DataImportFactory extends Factory
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
            'importer' => 'template',
            'name' => fake()->sentence(3),
            'status' => 'imported',
        ];
    }
}
