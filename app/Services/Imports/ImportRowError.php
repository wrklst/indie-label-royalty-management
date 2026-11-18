<?php

namespace App\Services\Imports;

/**
 * A statement line that could not be turned into a transaction.
 */
class ImportRowError
{
    public function __construct(public string $message, public bool $skipped = false) {}
}
