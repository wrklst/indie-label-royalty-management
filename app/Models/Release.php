<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLabel;
use Database\Factories\ReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['label_id', 'artist_id', 'contract_id', 'name', 'catalog_number', 'upc', 'image_path', 'released_on', 'spotify_id', 'discogs_id', 'accounts_mechanicals', 'mechanicals_rate', 'ppd_rate', 'reserves_rate', 'notes'])]
class Release extends Model
{
    /** @use HasFactory<ReleaseFactory> */
    use BelongsToLabel, HasFactory;

    /**
     * Every release gets an "[Additionals]" item that carries income and
     * costs entered by hand rather than imported for a specific product.
     */
    protected static function booted(): void
    {
        static::created(function (Release $release): void {
            $release->items()->create([
                'label_id' => $release->label_id,
                'is_additionals' => true,
                'name' => '[Additionals]',
                'catalog_number' => $release->catalog_number,
                'upc' => $release->upc,
            ]);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'released_on' => 'date:Y-m-d',
            'accounts_mechanicals' => 'boolean',
        ];
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

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
