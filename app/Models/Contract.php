<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLabel;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['label_id', 'name', 'memo'])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use BelongsToLabel, HasFactory;

    /**
     * @return HasMany<ContractSplit, $this>
     */
    public function splits(): HasMany
    {
        return $this->hasMany(ContractSplit::class);
    }

    /**
     * @return BelongsToMany<Contract, $this>
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'contract_links', 'parent_contract_id', 'child_contract_id')->withPivot('weight')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Contract, $this>
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'contract_links', 'child_contract_id', 'parent_contract_id')->withPivot('weight')->withTimestamps();
    }

    /**
     * @return HasMany<Release, $this>
     */
    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    /**
     * @return HasMany<Track, $this>
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
