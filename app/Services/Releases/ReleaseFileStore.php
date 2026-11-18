<?php

namespace App\Services\Releases;

use App\Enums\ReleaseFileKind;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\Track;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Stores the files of a release on the private disk.
 */
class ReleaseFileStore
{
    public const string DISK = 'local';

    /** @var list<string> */
    public const array COVER_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private readonly AudioPreviews $previews) {}

    /**
     * Keep an uploaded file with the release. Audio is attached to the
     * track its file name points at when no track is given, and queued
     * for its MP3 playback copy.
     *
     * @throws ValidationException when a cover is not a JPEG, PNG or WebP image
     */
    public function store(Release $release, UploadedFile $upload, ?ReleaseFileKind $kind = null, ?int $trackId = null, ?User $user = null): ReleaseFile
    {
        $mimeType = $upload->getMimeType();
        $extension = strtolower($upload->getClientOriginalExtension() ?: (string) $upload->guessExtension());

        // The extension becomes part of the stored path, which has to fit its column.
        if (preg_match('/^[a-z0-9]{1,10}$/', $extension) !== 1) {
            $extension = (string) $upload->guessExtension();
        }
        $kind ??= ReleaseFileKind::guess($mimeType, $extension);

        if ($kind === ReleaseFileKind::Cover && ! in_array($mimeType, self::COVER_TYPES, true)) {
            throw ValidationException::withMessages(['file' => 'A cover must be a JPEG, PNG or WebP image.']);
        }

        if ($kind === ReleaseFileKind::Audio) {
            $trackId ??= $this->guessTrack($release, $upload->getClientOriginalName())?->id;
        } else {
            $trackId = null;
        }

        $path = $upload->storeAs(
            "releases/{$release->label_id}/{$release->id}",
            Str::uuid()->toString().($extension !== '' ? ".{$extension}" : ''),
            self::DISK,
        );

        if ($path === false) {
            throw ValidationException::withMessages(['file' => 'The file could not be stored.']);
        }

        try {
            $file = DB::transaction(function () use ($release, $upload, $kind, $trackId, $user, $path, $mimeType): ReleaseFile {
                // A release shows one cover; an earlier one is kept as artwork.
                if ($kind === ReleaseFileKind::Cover) {
                    $release->files()->where('kind', ReleaseFileKind::Cover)->update(['kind' => ReleaseFileKind::Artwork]);
                }

                return $release->files()->create([
                    'label_id' => $release->label_id,
                    'track_id' => $trackId,
                    'user_id' => $user?->id,
                    'kind' => $kind,
                    'disk' => self::DISK,
                    'path' => $path,
                    'original_name' => Str::limit(basename($upload->getClientOriginalName()), 250, ''),
                    'mime_type' => $mimeType,
                    'size' => $upload->getSize(),
                    'checksum' => hash_file('sha256', $upload->getRealPath()) ?: null,
                ]);
            });
        } catch (Throwable $exception) {
            // Without its record nothing would ever remove the stored file.
            Storage::disk(self::DISK)->delete($path);

            throw $exception;
        }

        $this->previews->queue($file);

        return $file;
    }

    /**
     * Find the track a file such as "03 Example Track.wav" belongs to:
     * by ISRC, then by a leading track number, then by title.
     */
    public function guessTrack(Release $release, string $fileName): ?Track
    {
        $name = pathinfo($fileName, PATHINFO_FILENAME);
        $normalized = $this->normalize($name);
        $tracks = $release->tracks()->get();

        foreach ($tracks as $track) {
            if ($track->isrc !== null && str_contains(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name) ?? ''), $track->isrc)) {
                return $track;
            }
        }

        if (preg_match('/^\s*(?:\d{1,2}[-.]\s*)?(\d{1,3})(?!\d)/', $name, $match)) {
            $numbered = $tracks->where('track_number', (int) $match[1]);

            if ($numbered->count() === 1) {
                return $numbered->first();
            }
        }

        $byTitle = $tracks
            ->filter(fn (Track $track): bool => $this->normalize($track->name) !== '' && str_contains($normalized, $this->normalize($track->name)))
            ->sortByDesc(fn (Track $track): int => mb_strlen($track->name));

        return $byTitle->first();
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($value)) ?? '');
    }
}
