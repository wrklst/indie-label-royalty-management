<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLabel;
use Database\Factories\ArtistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['label_id', 'alias_of_id', 'name', 'image_path', 'spotify_id', 'discogs_search_name', 'notes'])]
class Artist extends Model
{
    /** @use HasFactory<ArtistFactory> */
    use BelongsToLabel, HasFactory;

    /**
     * @return BelongsTo<Artist, $this>
     */
    public function aliasOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'alias_of_id');
    }

    /**
     * @return HasMany<Artist, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(self::class, 'alias_of_id');
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
}
