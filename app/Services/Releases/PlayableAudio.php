<?php

namespace App\Services\Releases;

use App\Models\ReleaseFile;
use Illuminate\Support\Collection;

/**
 * Chooses which of a track's audio files the player asks for.
 */
class PlayableAudio
{
    /** Formats every browser plays without conversion. */
    private const array UNIVERSAL_TYPES = ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/aac'];

    /**
     * A format every browser plays if there is one, then one most browsers
     * play, otherwise the first upload. Null when the track has no audio.
     *
     * @param  Collection<int, ReleaseFile>|null  $files
     */
    public static function pick(?Collection $files): ?ReleaseFile
    {
        if ($files === null) {
            return null;
        }

        return $files->first(fn (ReleaseFile $file): bool => in_array($file->mime_type, self::UNIVERSAL_TYPES, true))
            ?? $files->first(fn (ReleaseFile $file): bool => $file->canBeShownInline())
            ?? $files->first();
    }
}
