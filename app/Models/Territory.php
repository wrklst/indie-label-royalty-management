<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLabel;
use Database\Factories\TerritoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['label_id', 'name', 'countries'])]
class Territory extends Model
{
    /** @use HasFactory<TerritoryFactory> */
    use BelongsToLabel, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'countries' => 'array',
        ];
    }

    /**
     * Whether the given ISO country code belongs to this territory.
     */
    public function includes(?string $country): bool
    {
        return $country !== null && in_array(strtoupper($country), $this->countries ?? [], true);
    }
}
