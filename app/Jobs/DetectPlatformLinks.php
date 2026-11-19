<?php

namespace App\Jobs;

use App\Models\Release;
use App\Models\Track;
use App\Services\Catalog\PlatformLinks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Looks for the platform pages of a release and its tracks, or of one track.
 */
class DetectPlatformLinks implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public ?int $releaseId = null, public ?int $trackId = null) {}

    public function handle(PlatformLinks $links): void
    {
        $record = $this->record();

        if ($record instanceof Release) {
            $links->detectRelease($record);
            $record->tracks()->withoutGlobalScope('label')->get()->each(fn (Track $track) => $links->detectTrack($track));
        } elseif ($record instanceof Track) {
            $links->detectTrack($record);
        }

        if ($record !== null) {
            PlatformLinks::mark($record, false);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $record = $this->record();

        if ($record !== null) {
            PlatformLinks::mark($record, false);
        }
    }

    private function record(): Release|Track|null
    {
        return $this->releaseId !== null
            ? Release::query()->withoutGlobalScope('label')->find($this->releaseId)
            : Track::query()->withoutGlobalScope('label')->find($this->trackId);
    }
}
