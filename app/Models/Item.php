<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLabel;
use Carbon\CarbonImmutable;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $label_id
 * @property int|null $release_id
 * @property int|null $track_id
 * @property int|null $contract_id
 * @property int|null $territory_id
 * @property int|null $data_import_id
 * @property string|null $format_group
 * @property bool $is_additionals
 * @property string $name
 * @property string|null $artist_name
 * @property string|null $track_name
 * @property string|null $catalog_number
 * @property string|null $isrc
 * @property string|null $iswc
 * @property string|null $upc
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label_id', 'release_id', 'track_id', 'contract_id', 'territory_id', 'data_import_id', 'format_group', 'is_additionals', 'name', 'artist_name', 'track_name', 'catalog_number', 'isrc', 'iswc', 'upc'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use BelongsToLabel, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_additionals' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Release, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * @return BelongsTo<Track, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<Territory, $this>
     */
    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
