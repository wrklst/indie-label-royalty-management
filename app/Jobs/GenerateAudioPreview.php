<?php

namespace App\Jobs;

use App\Enums\AudioPreviewStatus;
use App\Models\ReleaseFile;
use App\Services\Releases\AudioPreviews;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Makes the MP3 an audio master is played from. A file that cannot be
 * converted is recorded as failed rather than tried again and again;
 * the second attempt is for a worker that was stopped half way.
 */
class GenerateAudioPreview implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout;

    /** How long the lock lasts that keeps a file from being queued twice. */
    public int $uniqueFor;

    public function __construct(public int $releaseFileId, public bool $force = false)
    {
        // A little longer than ffmpeg itself may take, so its own timeout is the one that reports.
        $this->timeout = (int) config('royalties.audio.timeout') + 60;
        // As long as every attempt may take: a conversion whose process died frees the file soon after.
        $this->uniqueFor = $this->timeout * $this->tries + 60;
    }

    public function uniqueId(): string
    {
        return (string) $this->releaseFileId;
    }

    public function handle(AudioPreviews $previews): void
    {
        // Run after a web response, the request's own time limit would cut a long conversion short.
        // Not in a worker: the limit would outlive this job and end the worker during a later one.
        if (! app()->runningInConsole()) {
            set_time_limit($this->timeout);
        }

        $file = $this->file();

        if ($file !== null) {
            $previews->generate($file, $this->force);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->file()?->update([
            'preview_status' => AudioPreviewStatus::Failed,
            'preview_error' => Str::limit($exception?->getMessage() ?? 'The conversion did not finish.', 1000),
        ]);
    }

    /**
     * Workers have no label signed in; the file is looked up across labels.
     */
    private function file(): ?ReleaseFile
    {
        return ReleaseFile::query()->withoutGlobalScopes()->whereKey($this->releaseFileId)->first();
    }
}
