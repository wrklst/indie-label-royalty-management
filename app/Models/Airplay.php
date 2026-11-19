<?php

namespace App\Models;

use App\Enums\AirplayOrigin;
use App\Models\Concerns\BelongsToLabel;
use Carbon\CarbonImmutable;
use Database\Factories\AirplayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A track played by a broadcaster: read from a neighbouring rights
 * statement or entered by hand, and enriched with links found on it.
 *
 * @property int $id
 * @property int $label_id
 * @property int|null $track_id
 * @property int|null $data_import_id
 * @property AirplayOrigin $origin
 * @property string $track_name
 * @property string|null $artist_name
 * @property string|null $isrc
 * @property string $station
 * @property string|null $media_type
 * @property string|null $region
 * @property string|null $society
 * @property CarbonImmutable|null $period_start
 * @property CarbonImmutable|null $period_end
 * @property string|null $amount
 * @property list<array{url: string, title: string|null, description: string|null, source: string}>|null $links
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label_id', 'track_id', 'data_import_id', 'origin', 'track_name', 'artist_name', 'isrc', 'station', 'media_type', 'region', 'society', 'period_start', 'period_end', 'amount', 'links', 'notes'])]
class Airplay extends Model
{
    /** @use HasFactory<AirplayFactory> */
    use BelongsToLabel, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => AirplayOrigin::class,
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'amount' => 'decimal:15',
            'links' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Track, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /**
     * Add a link, or replace the one with the same address.
     *
     * @param  array{url: string, title?: string|null, description?: string|null}  $link
     */
    public function addLink(array $link, AirplayOrigin $source): void
    {
        $this->links = collect($this->links)->reject(fn (array $existing): bool => $existing['url'] === $link['url'])->push([
            'url' => $link['url'], 'title' => $link['title'] ?? null, 'description' => $link['description'] ?? null, 'source' => $source->value,
        ])->values()->all();
    }

    /**
     * What to look for on the web to find this play.
     */
    public function searchQuery(): string
    {
        return collect([$this->artist_name, $this->track_name])->filter()->map(fn (string $part): string => "\"{$part}\"")
            ->push($this->station)->implode(' ');
    }
}
