<?php

namespace App\Services\Releases;

/**
 * The resized variants an image of a release can be requested in.
 */
enum ReleaseImageSize: string
{
    /** A square crop for lists and the player. */
    case Thumb = 'thumb';

    /** The whole image, for the release page. */
    case Medium = 'medium';

    /**
     * The longest edge of the variant in pixels.
     */
    public function pixels(): int
    {
        return match ($this) {
            self::Thumb => 96,
            self::Medium => 600,
        };
    }

    /**
     * Whether the image is cut to a square rather than fitted into one.
     */
    public function isCropped(): bool
    {
        return $this === self::Thumb;
    }
}
