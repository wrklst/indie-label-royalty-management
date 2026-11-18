<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLabel;
use Carbon\CarbonImmutable;
use Database\Factories\TrackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $label_id
 * @property int $release_id
 * @property int|null $artist_id
 * @property int|null $contract_id
 * @property string $name
 * @property string|null $isrc
 * @property string|null $iswc
 * @property int|null $track_number
 * @property int|null $duration
 * @property string|float|int|null $mechanicals_rate
 * @property string|float|int|null $ppd_rate
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label_id', 'release_id', 'artist_id', 'contract_id', 'name', 'isrc', 'iswc', 'track_number', 'duration', 'mechanicals_rate', 'ppd_rate'])]
class Track extends Model
{
    /** @use HasFactory<TrackFactory> */
    use BelongsToLabel, HasFactory;

    /**
     * Items of a deleted track stay on the release when they carry
     * transactions; empty ones go with the track.
     */
    protected static function booted(): void
    {
        static::deleting(function (Track $track): void {
            $track->items()->doesntHave('transactions')->delete();
        });
    }

    /**
     * @return BelongsTo<Release, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * @return BelongsTo<Artist, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
