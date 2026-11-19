<?php

namespace App\Jobs;

use App\Models\Track;
use App\Services\Airplay\AirplayResearch;
use App\Services\Airplay\ResearchState;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

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
        $track = $this->subject();

        if ($track === null) {
            return;
        }

        ResearchState::set($track, ResearchState::RUNNING, 'Starting');

        $research->research($track, fn (int $step, int $total, string $query, string $region) => ResearchState::set(
            $track, ResearchState::RUNNING, "Search {$step} of {$total} ({$region}): {$query}",
        ));

        ResearchState::set($track, null);
    }

    public function failed(?Throwable $exception): void
    {
        $track = $this->subject();

        if ($track !== null) {
            ResearchState::set($track, ResearchState::FAILED, $exception?->getMessage() ?? 'The research stopped unexpectedly.');
        }
    }

    private function subject(): ?Track
    {
        return Track::query()->withoutGlobalScope('label')->find($this->trackId);
    }
}
