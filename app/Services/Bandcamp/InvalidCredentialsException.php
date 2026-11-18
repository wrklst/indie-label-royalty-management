<?php

namespace App\Services\Bandcamp;

/**
 * Bandcamp rejected the client id, client secret or token.
 */
class InvalidCredentialsException extends BandcampException
{
    public function __construct(?string $detail = null)
    {
        parent::__construct('Bandcamp did not accept the client id and client secret'.($detail ? " ({$detail})" : '').'. Compare them with Settings → API Access on Bandcamp and enter them again.');
    }
}
