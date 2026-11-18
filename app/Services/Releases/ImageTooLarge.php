<?php

namespace App\Services\Releases;

use RuntimeException;

/**
 * An image has more pixels than this server is willing to decode.
 */
class ImageTooLarge extends RuntimeException {}
