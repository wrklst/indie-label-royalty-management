<?php

namespace App\Services\Bandcamp;

/**
 * Bandcamp refuses to hand out a second set of tokens for a client that
 * already has an active grant, and the token of that grant is not ours.
 */
class DuplicateGrantException extends BandcampException
{
    public function __construct()
    {
        parent::__construct('Bandcamp says these credentials are already in use somewhere else, and the token that belongs to them is not stored here. On Bandcamp, open Settings → API Access, regenerate the client, then enter the new client id and secret here.');
    }
}
