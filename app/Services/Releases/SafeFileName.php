<?php

namespace App\Services\Releases;

/**
 * Names for files handed to other systems.
 */
class SafeFileName
{
    /**
     * Remove what file systems and zip tools choke on.
     */
    public static function from(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', preg_replace('/[\/\\\\:*?"<>|\x00-\x1F]/u', ' ', $name) ?? '') ?? '', ' .');

        return $name === '' ? 'untitled' : mb_substr($name, 0, 150);
    }
}
