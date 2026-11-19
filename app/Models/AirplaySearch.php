<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLabel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One web search that was run for a track or a play, with everything it
 * returned: the record of what was researched when.
 *
 * @property int $id
 * @property int $label_id
 * @property int|null $track_id
 * @property int|null $airplay_id
 * @property string $query
 * @property string|null $region
 * @property bool $succeeded
 * @property int $new_findings
 * @property string|null $error
 * @property list<array{title: string, url: string, description: string|null, domain: string|null}>|null $results
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label_id', 'track_id', 'airplay_id', 'query', 'region', 'succeeded', 'new_findings', 'error', 'results'])]
class AirplaySearch extends Model
{
    use BelongsToLabel;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['succeeded' => 'boolean', 'results' => 'array'];
    }

    /**
     * @return BelongsTo<Track, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /**
     * @return BelongsTo<Airplay, $this>
     */
    public function airplay(): BelongsTo
    {
        return $this->belongsTo(Airplay::class);
    }
}
