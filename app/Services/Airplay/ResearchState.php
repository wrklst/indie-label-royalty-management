<?php

namespace App\Services\Airplay;

use App\Models\Airplay;
use App\Models\Track;

/**
 * Where a research for a track or a play stands, written by the queue
 * job as it goes and read by the airplay page.
 */
class ResearchState
{
    public const string QUEUED = 'queued';

    public const string RUNNING = 'running';

    public const string FAILED = 'failed';

    /** A job is stopped by the queue after eleven minutes; "running" for longer than this is a job that died. */
    public const int STALE_MINUTES = 15;

    public static function set(Track|Airplay $subject, ?string $state, ?string $step = null): void
    {
        $subject->newQuery()->withoutGlobalScope('label')->whereKey($subject->getKey())->update([
            'research_state' => $state,
            'research_step' => $step === null ? null : mb_substr($step, 0, 500),
            'research_state_at' => $state === null ? null : now(),
        ]);
    }

    /**
     * What to show for a stored state: nothing for a run that died without reporting.
     */
    public static function visible(?string $state, mixed $since): ?string
    {
        if ($state === self::RUNNING && ($since === null || now()->subMinutes(self::STALE_MINUTES)->greaterThan($since))) {
            return null;
        }

        return $state;
    }
}
