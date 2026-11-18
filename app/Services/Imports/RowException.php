<?php

namespace App\Services\Imports;

use RuntimeException;

/**
 * Thrown while mapping one statement line: the line is left out, the
 * rest of the file is still imported.
 */
class RowException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $skipped = false)
    {
        parent::__construct($message);
    }
}
