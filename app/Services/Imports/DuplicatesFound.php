<?php

namespace App\Services\Imports;

use RuntimeException;

/**
 * Thrown inside an import's database transaction when lines look like
 * bookings that already exist, so nothing is saved until someone decides.
 */
class DuplicatesFound extends RuntimeException
{
    /**
     * @param  list<array<string, mixed>>  $proposals
     */
    public function __construct(public readonly array $proposals)
    {
        parent::__construct('The file holds possible duplicates of existing bookings.');
    }
}
