<?php

namespace Database\Factories;

use App\Models\Label;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
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
            'disk' => 'local',
            'path' => 'receipts/example.pdf',
            'original_name' => 'example-invoice.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'status' => Receipt::NEW,
        ];
    }
}
