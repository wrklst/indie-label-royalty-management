<?php

namespace App\Services\Bandcamp;

use Carbon\CarbonImmutable;

/**
 * What one sync run did.
 */
final readonly class SyncResult
{
    public function __construct(
        public int $windows,
        public int $imports,
        public int $transactions,
        public int $alreadyImported,
        public int $linesWithErrors,
        public CarbonImmutable $syncedUntil,
        public bool $complete = true,
    ) {}

    public function summary(): string
    {
        $summary = $this->transactions === 1
            ? '1 transaction imported'
            : "{$this->transactions} transactions imported";

        if ($this->alreadyImported > 0) {
            $summary .= ", {$this->alreadyImported} already imported before";
        }

        if ($this->linesWithErrors > 0) {
            $summary .= ", {$this->linesWithErrors} lines with errors (see Imports)";
        }

        $summary .= ". Sales are in up to {$this->syncedUntil->format('Y-m-d H:i')} UTC.";

        return $this->complete ? $summary : "{$summary} There is more to fetch: sync again to continue.";
    }
}
