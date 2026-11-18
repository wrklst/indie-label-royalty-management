<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

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
        return array_merge(...array_values(self::groups()));
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

        foreach (self::groups() as $group => $formats) {
            if (in_array($format, $formats, true)) {
                return $group;
            }
        }

        return null;
    }

    /**
     * The configured formats, keyed by the group they belong to.
     *
     * @return array<string, list<string>>
     */
    private static function groups(): array
    {
        return Config::array('royalties.formats');
    }
}
