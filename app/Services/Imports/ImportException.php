<?php

namespace App\Services\Imports;

use RuntimeException;

/**
 * Thrown when a file cannot be imported at all, e.g. wrong format.
 */
class ImportException extends RuntimeException {}
