<?php

namespace App\Jobs;

use App\Models\Airplay;
use App\Services\Airplay\AirplayResearch;
use App\Services\Airplay\ResearchState;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Looks closer at one airplay on the web: the show, the playlist, anything written about it.
 */
class ResearchAirplayEntry implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 660;

    public int $uniqueFor = 900;

    public function __construct(public int $airplayId) {}

    public function uniqueId(): string
    {
        return (string) $this->airplayId;
    }

    public function handle(AirplayResearch $research): void
    {
        $airplay = $this->subject();

        if ($airplay === null) {
            return;
        }

        ResearchState::set($airplay, ResearchState::RUNNING, 'Starting');

        $research->researchAirplay($airplay, fn (int $step, int $total, string $query, string $region) => ResearchState::set(
            $airplay, ResearchState::RUNNING, "Search {$step} of {$total} ({$region}): {$query}",
        ));

        ResearchState::set($airplay, null);
    }

    public function failed(?Throwable $exception): void
    {
        $airplay = $this->subject();

        if ($airplay !== null) {
            ResearchState::set($airplay, ResearchState::FAILED, $exception?->getMessage() ?? 'The research stopped unexpectedly.');
        }
    }

    private function subject(): ?Airplay
    {
        return Airplay::query()->withoutGlobalScope('label')->find($this->airplayId);
    }
}
