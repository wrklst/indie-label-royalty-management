<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractSplit;
use App\Models\Payee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractSplit>
 */
class ContractSplitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'payee_id' => Payee::factory(),
            'split_amount' => '0.5',
        ];
    }
}
