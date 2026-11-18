<?php

namespace App\Services\Bandcamp;

use RuntimeException;

/**
 * Something went wrong talking to Bandcamp or importing its sales. The
 * message is written for the label admin and safe to show as it is.
 */
class BandcampException extends RuntimeException {}
