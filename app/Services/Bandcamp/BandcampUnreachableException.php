<?php

namespace App\Services\Bandcamp;

/**
 * Bandcamp did not answer: no connection, a timeout or a server error
 * that outlasted the retries.
 */
class BandcampUnreachableException extends BandcampException
{
    public function __construct(?string $detail = null)
    {
        parent::__construct('Bandcamp could not be reached'.($detail ? " ({$detail})" : '').'. Nothing was changed; try again in a few minutes.');
    }
}
