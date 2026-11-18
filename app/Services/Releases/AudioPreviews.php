<?php

namespace App\Services\Releases;

use App\Enums\AudioPreviewStatus;
use App\Jobs\GenerateAudioPreview;
use App\Models\ReleaseFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The MP3 copies audio is played from. The uploaded master stays as it
 * is; ffmpeg makes a copy for listening, kept on the same private disk
 * in a "previews" folder next to the master. A file that already is an
 * MP3 is played as it is and gets no copy.
 */
class AudioPreviews
{
    /**
     * Mark the file as waiting for its playback copy and start the
     * conversion: right after the response by default, or on the queue
     * when the label runs a worker. An MP3 can be played straight away.
     */
    public function queue(ReleaseFile $file, bool $force = false): void
    {
        if (! $file->isAudio()) {
            return;
        }

        $file->update([
            'preview_status' => $this->isOwnPreview($file) ? AudioPreviewStatus::Ready : AudioPreviewStatus::Pending,
            'preview_error' => null,
        ]);

        if (config('royalties.audio.queue')) {
            GenerateAudioPreview::dispatch($file->id, $force);

            return;
        }

        // No worker needed: converted in this process once the response has been sent.
        GenerateAudioPreview::dispatchAfterResponse($file->id, $force);
    }

    /**
     * Make the playback copy now, unless a usable one exists and $force
     * is not set. Returns whether the file can be played as MP3
     * afterwards; when not, the reason is recorded on the file.
     */
    public function generate(ReleaseFile $file, bool $force = false): bool
    {
        if (! $file->isAudio()) {
            $this->discard($file);

            return false;
        }

        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->path)) {
            return $this->fail($file, 'The stored file is missing.');
        }

        $source = $disk->path($file->path);

        if ($this->isOwnPreview($file)) {
            $this->forget($file);
            $file->update([
                'preview_path' => null,
                'preview_status' => AudioPreviewStatus::Ready,
                'preview_error' => null,
                'duration' => $file->duration ?? $this->probeDuration($source),
            ]);
            $this->fillTrackDuration($file);

            return true;
        }

        if (! $force && $this->path($file) !== null) {
            return true;
        }

        $target = $this->directory($file).'/'.$file->id.'-'.substr($file->checksum ?? hash('sha256', $file->path), 0, 16).'.mp3';
        $disk->makeDirectory($this->directory($file));

        // Written under a temporary name first, so a half-written copy is never played.
        $temporary = $disk->path($target).'.'.bin2hex(random_bytes(4)).'.tmp';

        try {
            $result = Process::timeout((int) config('royalties.audio.timeout'))->run($this->command($file, $source, $temporary));
        } catch (Throwable $exception) {
            @unlink($temporary);

            return $this->fail($file, 'ffmpeg could not be run: '.$exception->getMessage());
        }

        if ($result->failed() || ! is_file($temporary) || filesize($temporary) === 0 || ! rename($temporary, $disk->path($target))) {
            @unlink($temporary);

            return $this->fail($file, $this->lastLines($result->errorOutput()) ?: 'ffmpeg wrote no file.');
        }

        $this->forget($file, except: $target);
        $file->update([
            'preview_path' => $target,
            'preview_status' => AudioPreviewStatus::Ready,
            'preview_error' => null,
            'duration' => $this->parseDuration($result->errorOutput()) ?? $file->duration,
        ]);
        $this->fillTrackDuration($file);

        return true;
    }

    /**
     * The path on the file's disk of its finished playback copy, or null
     * when there is none (not made yet, failed, or the file is an MP3
     * that is played as it is).
     */
    public function path(ReleaseFile $file): ?string
    {
        if ($file->preview_status !== AudioPreviewStatus::Ready || $file->preview_path === null) {
            return null;
        }

        return Storage::disk($file->disk)->exists($file->preview_path) ? $file->preview_path : null;
    }

    /**
     * Whether the file is an MP3 already and needs no copy.
     */
    public function isOwnPreview(ReleaseFile $file): bool
    {
        return $file->mime_type === 'audio/mpeg' || strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION)) === 'mp3';
    }

    /**
     * Remove the playback copy and what is recorded about it, for a file
     * that is no longer audio.
     */
    public function discard(ReleaseFile $file): void
    {
        $this->forget($file);
        $file->update(['preview_path' => null, 'preview_status' => null, 'preview_error' => null]);
    }

    /**
     * Delete the stored playback copies of a file from disk.
     */
    public function forget(ReleaseFile $file, ?string $except = null): void
    {
        $disk = Storage::disk($file->disk);
        $directory = $this->directory($file);

        if (! $disk->exists($directory)) {
            return;
        }

        $disk->delete(array_values(array_filter(
            $disk->files($directory),
            fn (string $path): bool => $path !== $except && str_starts_with(basename($path), $file->id.'-'),
        )));
    }

    /**
     * The ffmpeg call, as separate arguments: nothing in it passes
     * through a shell, so file names and titles cannot be read as
     * commands. Only the first audio stream is kept (no cover picture or
     * video), the tags of the master are dropped and title, artist and
     * album are written from the track and its release.
     *
     * @return list<string>
     */
    public function command(ReleaseFile $file, string $source, string $target): array
    {
        $command = [
            (string) config('royalties.audio.ffmpeg'), '-hide_banner', '-nostdin', '-y',
            '-i', $source,
            '-map', '0:a:0', '-vn', '-sn', '-dn', '-map_metadata', '-1', '-map_chapters', '-1',
            '-c:a', 'libmp3lame', '-b:a', ((int) config('royalties.audio.bitrate')).'k',
            '-ar', (string) (int) config('royalties.audio.sample_rate'), '-ac', '2',
            '-id3v2_version', '3',
        ];

        foreach ($this->tags($file) as $tag => $value) {
            array_push($command, '-metadata', "{$tag}={$value}");
        }

        return [...$command, '-f', 'mp3', $target];
    }

    /**
     * @return array<string, string>
     */
    private function tags(ReleaseFile $file): array
    {
        $track = $file->track;

        if ($track === null) {
            return [];
        }

        $release = $file->release;

        return array_filter([
            'title' => $track->name.($track->version ? " ({$track->version})" : ''),
            'artist' => ArtistCredits::display($track) ?? ArtistCredits::display($release) ?? '',
            'album' => $release->name.($release->version ? " ({$release->version})" : ''),
        ], fn (string $value): bool => $value !== '');
    }

    private function directory(ReleaseFile $file): string
    {
        return trim(dirname($file->path), './').'/previews';
    }

    /**
     * Ask ffmpeg how long a file plays, in seconds. Null when it cannot say.
     */
    private function probeDuration(string $source): ?int
    {
        try {
            // Without an output ffmpeg describes the input and stops.
            $result = Process::timeout(60)->run([(string) config('royalties.audio.ffmpeg'), '-hide_banner', '-nostdin', '-i', $source]);
        } catch (Throwable) {
            return null;
        }

        return $this->parseDuration($result->errorOutput());
    }

    /**
     * Read "Duration: 00:03:25.12" from what ffmpeg reports about its input.
     */
    private function parseDuration(string $output): ?int
    {
        if (preg_match('/Duration:\s*(\d+):(\d{2}):(\d{2}(?:\.\d+)?)/', $output, $match) !== 1) {
            return null;
        }

        $seconds = (int) round((int) $match[1] * 3600 + (int) $match[2] * 60 + (float) $match[3]);

        return $seconds > 0 ? $seconds : null;
    }

    /**
     * A track without a length gets the one of its audio.
     */
    private function fillTrackDuration(ReleaseFile $file): void
    {
        $track = $file->track;

        if ($file->duration !== null && $track !== null && ($track->duration === null || $track->duration === 0)) {
            $track->update(['duration' => $file->duration]);
        }
    }

    private function fail(ReleaseFile $file, string $reason): bool
    {
        $file->update(['preview_status' => AudioPreviewStatus::Failed, 'preview_error' => Str::limit($reason, 1000)]);

        return false;
    }

    /**
     * ffmpeg says what went wrong at the end of what it prints.
     */
    private function lastLines(string $output): string
    {
        $lines = array_values(array_filter(array_map(trim(...), explode("\n", $output)), fn (string $line): bool => $line !== ''));

        return implode("\n", array_slice($lines, -3));
    }
}
