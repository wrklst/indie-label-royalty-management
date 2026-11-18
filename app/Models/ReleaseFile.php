<?php

namespace App\Models;

use App\Enums\ReleaseFileKind;
use App\Models\Concerns\BelongsToLabel;
use Carbon\CarbonImmutable;
use Database\Factories\ReleaseFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file kept with a release: cover, audio master, artwork, documents.
 * Files live on a private disk and are only ever served through
 * ReleaseFileController, never by a public URL.
 *
 * @property int $id
 * @property int $label_id
 * @property int $release_id
 * @property int|null $track_id
 * @property int|null $user_id
 * @property ReleaseFileKind $kind
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size
 * @property string|null $checksum
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Release $release
 * @property-read Track|null $track
 */
#[Fillable(['label_id', 'release_id', 'track_id', 'user_id', 'kind', 'disk', 'path', 'original_name', 'mime_type', 'size', 'checksum'])]
class ReleaseFile extends Model
{
    /** @use HasFactory<ReleaseFileFactory> */
    use BelongsToLabel, HasFactory;

    /** Types a browser may display itself; everything else is downloaded. */
    private const array INLINE_TYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf',
        'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave', 'audio/flac', 'audio/x-flac', 'audio/mp4',
        'audio/x-m4a', 'audio/aac', 'audio/ogg', 'audio/aiff', 'audio/x-aiff',
    ];

    /**
     * The stored file goes when its record goes.
     */
    protected static function booted(): void
    {
        static::deleted(function (ReleaseFile $file): void {
            Storage::disk($file->disk)->delete($file->path);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ReleaseFileKind::class,
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Release, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * @return BelongsTo<Track, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function isAudio(): bool
    {
        return $this->kind === ReleaseFileKind::Audio;
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    }

    /**
     * Whether the browser may render the file in place. Anything that
     * could carry script (SVG, HTML) is always sent as a download.
     */
    public function canBeShownInline(): bool
    {
        return in_array($this->mime_type, self::INLINE_TYPES, true);
    }
}
