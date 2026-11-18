<?php

namespace App\Services\Releases;

use App\Enums\ArtistRole;
use App\Models\Artist;
use App\Models\Release;
use App\Models\Track;

/**
 * Turns the main artist and the additional credits of a release or track
 * into the names shown to people and written into exports.
 */
class ArtistCredits
{
    /**
     * "Main Artist & Second Artist feat. Guest".
     */
    public static function display(Release|Track $record): ?string
    {
        $main = self::names($record, ArtistRole::Primary);
        $featuring = self::names($record, ArtistRole::Featuring);

        if ($main === []) {
            return null;
        }

        return implode(' & ', $main).($featuring === [] ? '' : ' feat. '.implode(' & ', $featuring));
    }

    /**
     * The names credited in one role. For the primary role the main
     * artist of the record comes first.
     *
     * @return list<string>
     */
    public static function names(Release|Track $record, ArtistRole $role): array
    {
        $names = $record->artists
            ->filter(fn (Artist $artist): bool => $artist->pivot->role === $role->value)
            ->map(fn (Artist $artist): string => $artist->name)
            ->values()
            ->all();

        if ($role === ArtistRole::Primary && $record->artist !== null) {
            array_unshift($names, $record->artist->name);
        }

        return array_values(array_unique($names));
    }

    /**
     * Replace the additional credits of a release or track.
     *
     * @param  list<array{artist_id: int|string, role: string}>  $credits
     */
    public static function sync(Release|Track $record, array $credits): void
    {
        $record->artists()->detach();

        foreach ($credits as $position => $credit) {
            if ((int) $credit['artist_id'] === $record->artist_id && $credit['role'] === ArtistRole::Primary->value) {
                continue;
            }

            $record->artists()->attach((int) $credit['artist_id'], ['role' => $credit['role'], 'position' => $position]);
        }
    }
}
