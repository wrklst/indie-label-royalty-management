<?php

namespace App\Models;

use App\Enums\ReleaseFileKind;
use App\Enums\ReleaseStatus;
use App\Enums\ReleaseType;
use App\Models\Concerns\BelongsToLabel;
use Carbon\CarbonImmutable;
use Database\Factories\ReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $label_id
 * @property int|null $artist_id
 * @property int|null $contract_id
 * @property string $name
 * @property string|null $catalog_number
 * @property string|null $upc
 * @property string|null $image_path
 * @property CarbonImmutable|null $released_on
 * @property string|null $spotify_id
 * @property string|null $discogs_id
 * @property bool $accounts_mechanicals
 * @property string|float|int|null $mechanicals_rate
 * @property string|float|int|null $ppd_rate
 * @property string|float|int|null $reserves_rate
 * @property string|null $notes
 * @property ReleaseStatus $status
 * @property ReleaseType|null $release_type
 * @property string|null $version
 * @property string|null $genre
 * @property string|null $subgenre
 * @property string|null $language
 * @property string|null $explicit
 * @property string|null $p_line
 * @property string|null $c_line
 * @property CarbonImmutable|null $original_released_on
 * @property CarbonImmutable|null $preorder_on
 * @property string|null $press_release
 * @property string|null $key_facts
 * @property string|null $todo
 * @property string|null $files_url
 * @property list<string>|null $blocked_services
 * @property list<string>|null $blocked_territories
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label_id', 'artist_id', 'contract_id', 'name', 'catalog_number', 'upc', 'image_path', 'released_on', 'spotify_id', 'discogs_id', 'accounts_mechanicals', 'mechanicals_rate', 'ppd_rate', 'reserves_rate', 'notes', 'status', 'release_type', 'version', 'genre', 'subgenre', 'language', 'explicit', 'p_line', 'c_line', 'original_released_on', 'preorder_on', 'press_release', 'key_facts', 'todo', 'files_url', 'blocked_services', 'blocked_territories'])]
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

        static::updated(function (Release $release): void {
            if ($release->wasChanged(['catalog_number', 'upc'])) {
                $release->items()->where('is_additionals', true)->update([
                    'catalog_number' => $release->catalog_number,
                    'upc' => $release->upc,
                ]);
            }
        });

        // Items that carry transactions stay behind as unassigned items so
        // their income can be given to another release; empty ones go.
        static::deleting(function (Release $release): void {
            // Stored files are removed one by one so each is deleted from disk.
            $release->files()->get()->each->delete();

            $release->items()->doesntHave('transactions')->delete();
            $release->items()->where('is_additionals', true)->update([
                'is_additionals' => false,
                'name' => "[Additionals] {$release->name}",
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
            'original_released_on' => 'date:Y-m-d',
            'preorder_on' => 'date:Y-m-d',
            'status' => ReleaseStatus::class,
            'release_type' => ReleaseType::class,
            'blocked_services' => 'array',
            'blocked_territories' => 'array',
        ];
    }

    /**
     * Every artist credited on the release, main artists first.
     *
     * @return BelongsToMany<Artist, $this>
     */
    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class)->withPivot(['role', 'position'])->withTimestamps()->orderByPivot('position');
    }

    /**
     * @return HasMany<ReleaseFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ReleaseFile::class);
    }

    /**
     * The newest file marked as cover.
     *
     * @return HasOne<ReleaseFile, $this>
     */
    public function cover(): HasOne
    {
        return $this->hasOne(ReleaseFile::class)->where('kind', ReleaseFileKind::Cover)->latestOfMany();
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
