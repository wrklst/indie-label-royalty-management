<?php

namespace App\Jobs;

use App\Models\Track;
use App\Services\Airplay\AirplayResearch;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Researches one track on the web. A failed search leaves the track due,
 * so the next run picks it up again.
 */
class ResearchTrackAirplay implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** The research stops starting searches after seven minutes; this leaves the last one time to end, below Horizon's worker timeout. */
    public int $timeout = 660;

    public int $uniqueFor = 900;

    public function __construct(public int $trackId) {}

    public function uniqueId(): string
    {
        return (string) $this->trackId;
    }

    public function handle(AirplayResearch $research): void
    {
        $track = Track::query()->withoutGlobalScope('label')->find($this->trackId);

        if ($track !== null) {
            $research->research($track);
        }
    }
}
