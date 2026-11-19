<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLabel;
use Carbon\CarbonImmutable;
use Database\Factories\AirplayFindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A page the automatic research found for a track. It waits until
 * someone keeps it as (part of) an airplay or dismisses it; a dismissed
 * page stays so the next research does not bring it back.
 *
 * @property int $id
 * @property int $label_id
 * @property int|null $track_id
 * @property int|null $airplay_id
 * @property string $status
 * @property bool $is_radio
 * @property string|null $station
 * @property string $url
 * @property string $url_hash
 * @property string $title
 * @property string|null $description
 * @property string|null $domain
 * @property string $query
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label_id', 'track_id', 'airplay_id', 'status', 'is_radio', 'station', 'url', 'url_hash', 'title', 'description', 'domain', 'query'])]
class AirplayFinding extends Model
{
    /** @use HasFactory<AirplayFindingFactory> */
    use BelongsToLabel, HasFactory;

    public const string NEW = 'new';

    public const string KEPT = 'kept';

    public const string DISMISSED = 'dismissed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_radio' => 'boolean'];
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
