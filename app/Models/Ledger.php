<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLabel;
use Carbon\CarbonImmutable;
use Database\Factories\LedgerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $label_id
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label_id', 'name'])]
class Ledger extends Model
{
    /** @use HasFactory<LedgerFactory> */
    use BelongsToLabel, HasFactory;

    /**
     * @return HasMany<ContractSplit, $this>
     */
    public function splits(): HasMany
    {
        return $this->hasMany(ContractSplit::class);
    }
}
