<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLabel;
use Carbon\CarbonImmutable;
use Database\Factories\PlatformLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Where a release or a track can be heard or bought: its page on a
 * streaming service, a shop or a platform such as Bandcamp.
 *
 * @property int $id
 * @property int $label_id
 * @property int|null $release_id
 * @property int|null $track_id
 * @property string $platform A key of PlatformLinks::PLATFORMS, or "other".
 * @property string $url
 * @property string $origin
 * @property CarbonImmutable|null $dismissed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label_id', 'release_id', 'track_id', 'platform', 'url', 'origin', 'dismissed_at'])]
class PlatformLink extends Model
{
    /** @use HasFactory<PlatformLinkFactory> */
    use BelongsToLabel, HasFactory;

    public const string MANUAL = 'manual';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dismissed_at' => 'immutable_datetime',
        ];
    }
}
