<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLabel;
use Carbon\CarbonImmutable;
use Database\Factories\TrackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property bool|null $accounts_mechanicals Null follows the release.
 * @property string|float|int|null $mechanicals_rate
 * @property string|float|int|null $ppd_rate
 * @property int|null $disc_number
 * @property string|null $version
 * @property string|null $genre
 * @property string|null $language
 * @property string|null $explicit
 * @property string|float|int|null $bpm
 * @property string|null $musical_key
 * @property int|null $preview_start
 * @property string|null $p_line
 * @property array<string, list<string>>|null $credits
 * @property string|null $lyrics
 * @property string|null $ai_recording How much of the recording AI made: "none", "partly", "all"; null is not declared.
 * @property string|null $ai_composition
 * @property string|null $ai_lyrics
 * @property CarbonImmutable|null $researched_at
 * @property string|null $research_state
 * @property string|null $research_step
 * @property CarbonImmutable|null $research_state_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label_id', 'release_id', 'artist_id', 'contract_id', 'name', 'isrc', 'iswc', 'track_number', 'duration', 'accounts_mechanicals', 'mechanicals_rate', 'ppd_rate', 'disc_number', 'version', 'genre', 'language', 'explicit', 'bpm', 'musical_key', 'preview_start', 'p_line', 'credits', 'lyrics', 'researched_at', 'ai_recording', 'ai_composition', 'ai_lyrics'])]
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accounts_mechanicals' => 'boolean',
            'credits' => 'array',
            'researched_at' => 'immutable_datetime',
            'research_state_at' => 'immutable_datetime',
        ];
    }

    /**
     * Every artist credited on the track, main artists first.
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
     * The ids of every entry of this recording. A track that is on
     * several releases has one entry per release, all with the same ISRC.
     *
     * @return list<int>
     */
    public function recordingIds(): array
    {
        if ($this->isrc === null) {
            return [$this->id];
        }

        return array_values(self::query()->withoutGlobalScope('label')->where('label_id', $this->label_id)->where('isrc', $this->isrc)->orderBy('id')->pluck('id')->all());
    }

    /**
     * @return HasMany<Airplay, $this>
     */
    public function airplays(): HasMany
    {
        return $this->hasMany(Airplay::class);
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
