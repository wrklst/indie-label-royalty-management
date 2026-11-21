<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLabel;
use Carbon\CarbonImmutable;
use Database\Factories\PlayCountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How often a platform says a track was played in a period, as its
 * artist portal shows it. Statements say what was paid for; this is the
 * platform's own count to hold against them.
 *
 * @property int $id
 * @property int $label_id
 * @property int|null $track_id
 * @property string $batch
 * @property string $source_file
 * @property string $platform A key of Platforms::NAMES.
 * @property string $track_name
 * @property string|null $artist_name
 * @property string|null $isrc
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property int $plays
 * @property int|null $listeners
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label_id', 'track_id', 'batch', 'source_file', 'platform', 'track_name', 'artist_name', 'isrc', 'period_start', 'period_end', 'plays', 'listeners'])]
class PlayCount extends Model
{
    /** @use HasFactory<PlayCountFactory> */
    use BelongsToLabel, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'plays' => 'integer',
            'listeners' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Track, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
