<?php

namespace App\Support;

/**
 * Lookup helpers for the configured transaction formats.
 */
class Formats
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_merge(...array_values(config('royalties.formats')));
    }

    /**
     * Return the configured spelling of a format, or the value unchanged
     * when it is not a known format.
     */
    public static function canonical(?string $format): ?string
    {
        $format = trim((string) $format);

        if ($format === '') {
            return null;
        }

        foreach (self::all() as $known) {
            if (strcasecmp($known, $format) === 0) {
                return $known;
            }
        }

        return $format;
    }

    public static function group(?string $format): ?string
    {
        if ($format === null) {
            return null;
        }

        foreach (config('royalties.formats') as $group => $formats) {
            if (in_array($format, $formats, true)) {
                return $group;
            }
        }

        return null;
    }
}
