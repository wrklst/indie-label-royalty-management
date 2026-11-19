<?php

namespace App\Jobs;

use App\Models\Airplay;
use App\Services\Airplay\AirplayResearch;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
        $airplay = Airplay::query()->withoutGlobalScope('label')->find($this->airplayId);

        if ($airplay !== null) {
            $research->researchAirplay($airplay);
        }
    }
}
