<?php

namespace App\Services\Releases;

use App\Enums\ReleaseFileKind;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\Track;
use App\Support\WorkingCopy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * One release as the ZIP file Subvert's uploader accepts: the cover and
 * one audio file per track, flat, named so they sort in track order.
 *
 * Subvert reads the track order from the file names, takes a file named
 * "cover" as the cover art and uses the name of the ZIP as the release
 * title. Bonus files, pricing, credits and rights are entered on its
 * form and are not part of the ZIP.
 *
 * @see https://help.subvert.fm/hc/en-us/articles/57627270385299
 */
class SubvertPackage
{
    /** "ZIPs up to 2 GB are supported"; counted in decimal units to stay on the safe side. */
    public const int MAX_BYTES = 2_000_000_000;

    /** "Cover art files must be under 10 MB." */
    public const int MAX_COVER_BYTES = 10_000_000;

    /** Formats Subvert accepts, by file extension, with the lossless ones first. */
    private const array LOSSLESS = ['wav', 'aif', 'aiff', 'flac'];

    private const array LOSSY = ['mp3'];

    /** What a zip entry costs on top of its content: headers and its name, generously. */
    private const int ENTRY_OVERHEAD = 1024;

    /**
     * The name of the download, which becomes the release title on Subvert.
     */
    public function fileName(Release $release): string
    {
        return SafeFileName::from($release->name.($release->version ? " ({$release->version})" : '')).'.zip';
    }

    /**
     * Everything that keeps the release from being uploaded to Subvert,
     * as sentences for the user. Empty when the package can be built.
     *
     * @return list<string>
     */
    public function problems(Release $release): array
    {
        $problems = [];
        $bytes = 0;
        $cover = $this->cover($release);

        if ($cover === null) {
            $problems[] = 'The release has no cover.';
        } elseif (($size = $this->size($cover)) === null) {
            $problems[] = "The cover “{$cover->original_name}” is missing from storage.";
        } elseif ($size >= self::MAX_COVER_BYTES) {
            $problems[] = sprintf('The cover is %s MB; Subvert accepts covers under 10 MB.', number_format($size / 1_000_000, 1));
        } else {
            $bytes += $size + self::ENTRY_OVERHEAD;
        }

        $tracks = $this->tracks($release);

        if ($tracks->isEmpty()) {
            $problems[] = 'The release has no tracks.';
        }

        foreach ($tracks as $track) {
            $label = $this->trackLabel($track);
            $files = $this->audioFiles($release, $track);
            $audio = $this->pick($files);

            if ($files->isEmpty()) {
                $problems[] = "{$label} has no audio file.";
            } elseif ($audio === null) {
                $problems[] = "{$label} only has audio Subvert does not accept (“{$files->first()->original_name}”); it takes MP3, FLAC, WAV or AIFF.";
            } elseif (($size = $this->size($audio)) === null) {
                $problems[] = "The audio file “{$audio->original_name}” of {$label} is missing from storage.";
            } else {
                $bytes += $size + self::ENTRY_OVERHEAD;
            }
        }

        if ($bytes > self::MAX_BYTES) {
            $problems[] = sprintf('The files add up to %s GB; Subvert accepts ZIP files up to 2 GB.', number_format($bytes / 1_000_000_000, 2));
        }

        return $problems;
    }

    /**
     * The files of the package: name inside the ZIP => stored file.
     *
     * @return array<string, ReleaseFile>
     */
    public function entries(Release $release): array
    {
        $entries = [];
        $cover = $this->cover($release);

        if ($cover !== null) {
            $entries['cover'.$this->suffix($cover)] = $cover;
        }

        $tracks = $this->tracks($release);
        $severalDiscs = $tracks->map(fn (Track $track): int => $track->disc_number ?? 1)->unique()->count() > 1;
        $numbers = $this->numbers($tracks);
        $width = max(2, strlen((string) max([0, ...$numbers])));

        foreach ($tracks as $track) {
            $audio = $this->pick($this->audioFiles($release, $track));

            if ($audio === null) {
                continue;
            }

            $name = SafeFileName::from(sprintf(
                '%s%s %s%s',
                $severalDiscs ? ($track->disc_number ?? 1).'-' : '',
                str_pad((string) $numbers[$track->id], $width, '0', STR_PAD_LEFT),
                $track->name,
                $track->version ? " ({$track->version})" : '',
            ));

            $entries[$name.$this->suffix($audio)] = $audio;
        }

        return $entries;
    }

    /**
     * Build the archive and return the path of the temporary zip file,
     * which the caller removes. Files are added by path and stored
     * without compression, so nothing is read into memory.
     */
    public function build(Release $release): string
    {
        $path = tempnam(sys_get_temp_dir(), 'subvert-package');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The package could not be created.');
        }

        // Files from a bucket are downloaded first and have to stay until the archive is closed.
        $copies = [];

        foreach ($this->entries($release) as $name => $file) {
            $copies[] = $copy = WorkingCopy::of($file->disk, $file->path);
            $zip->addFile($copy->path, $name);
            $zip->setCompressionName($name, ZipArchive::CM_STORE);
        }

        if (! $zip->close()) {
            @unlink($path);

            throw new RuntimeException('The package could not be written.');
        }

        return $path;
    }

    private function cover(Release $release): ?ReleaseFile
    {
        return $release->files->where('kind', ReleaseFileKind::Cover)->sortByDesc('id')->first();
    }

    /**
     * @return Collection<int, Track>
     */
    private function tracks(Release $release): Collection
    {
        return $release->tracks
            ->sortBy(fn (Track $track): array => [$track->disc_number ?? 1, $track->track_number ?? PHP_INT_MAX, $track->id])
            ->values();
    }

    /**
     * The number each track is filed under, by track id: its own track
     * number when every track has one and none repeats on a disc,
     * otherwise its position on the disc.
     *
     * @param  Collection<int, Track>  $tracks
     * @return array<int, int>
     */
    private function numbers(Collection $tracks): array
    {
        $own = $tracks->map(fn (Track $track): string => ($track->disc_number ?? 1).'-'.($track->track_number ?? ''));
        $usable = $tracks->every(fn (Track $track): bool => $track->track_number !== null && $track->track_number > 0)
            && $own->unique()->count() === $tracks->count();

        $numbers = [];
        $positions = [];

        foreach ($tracks as $track) {
            $disc = $track->disc_number ?? 1;
            $positions[$disc] = ($positions[$disc] ?? 0) + 1;
            $numbers[$track->id] = $usable ? (int) $track->track_number : $positions[$disc];
        }

        return $numbers;
    }

    /**
     * @return Collection<int, ReleaseFile>
     */
    private function audioFiles(Release $release, Track $track): Collection
    {
        return $release->files
            ->filter(fn (ReleaseFile $file): bool => $file->kind === ReleaseFileKind::Audio && $file->track_id === $track->id)
            ->sortByDesc(fn (ReleaseFile $file): array => [$file->created_at?->getTimestamp() ?? 0, $file->id])
            ->values();
    }

    /**
     * The one file sent for a track: lossless before MP3, and the most
     * recently uploaded among equals.
     *
     * @param  Collection<int, ReleaseFile>  $files  newest first
     */
    private function pick(Collection $files): ?ReleaseFile
    {
        return $files->first(fn (ReleaseFile $file): bool => in_array($this->extension($file), self::LOSSLESS, true))
            ?? $files->first(fn (ReleaseFile $file): bool => in_array($this->extension($file), self::LOSSY, true));
    }

    private function extension(ReleaseFile $file): string
    {
        return strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
    }

    private function suffix(ReleaseFile $file): string
    {
        return $this->extension($file) === '' ? '' : '.'.$this->extension($file);
    }

    /**
     * The size of the stored file in bytes, or null when it is gone.
     */
    private function size(ReleaseFile $file): ?int
    {
        $disk = Storage::disk($file->disk);

        return $disk->exists($file->path) ? $disk->size($file->path) : null;
    }

    private function trackLabel(Track $track): string
    {
        return ($track->track_number !== null ? "Track {$track->track_number}" : 'The track')." “{$track->name}”";
    }
}
