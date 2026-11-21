<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLabel;
use Carbon\CarbonImmutable;
use Database\Factories\TerritoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $label_id
 * @property string $name
 * @property list<string> $countries
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
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

    /**
     * Why the territory cannot be deleted, or null when it can.
     */
    public function deleteBlock(): ?string
    {
        return ContractSplit::query()->where('territory_id', $this->id)->exists()
            ? 'This territory is used by contract splits. Change those splits first.'
            : null;
    }
}
