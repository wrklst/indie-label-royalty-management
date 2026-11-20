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
        $attached = [];

        foreach ($credits as $position => $credit) {
            if ((int) $credit['artist_id'] === $record->artist_id && $credit['role'] === ArtistRole::Primary->value) {
                continue;
            }

            // An artist holds a role once; a repeated credit would break the unique index.
            $key = ((int) $credit['artist_id']).'|'.$credit['role'];

            if (isset($attached[$key])) {
                continue;
            }

            $attached[$key] = true;

            $record->artists()->attach((int) $credit['artist_id'], ['role' => $credit['role'], 'position' => $position]);
        }
    }

    /**
     * Credit as remixer every artist of the catalog that a track title
     * names: "Title - Example Artist Remix", "Title (Example Artist Remix)".
     * A statement names one artist per track, so a remixer imported from
     * one is in the catalog but on no track. Names that match no artist
     * are left alone, as are credits that exist.
     *
     * @return list<string> One line per credit added.
     */
    public static function creditRemixers(): array
    {
        $artists = Artist::query()->get(['id', 'label_id', 'name'])
            ->keyBy(fn (Artist $artist): string => $artist->label_id.'|'.mb_strtolower($artist->name));
        $credited = [];

        foreach (Track::query()->with('artists')->orderBy('id')->get() as $track) {
            if (preg_match('/^.*(?:\s-\s+|\()(.+?)\s+(?:remix|rework|edit|mix|dub|version)\)?\s*$/iu', $track->name, $matches) !== 1) {
                continue;
            }

            $artist = $artists[$track->label_id.'|'.mb_strtolower(trim($matches[1]))] ?? null;

            if ($artist === null || $track->artists->contains(fn (Artist $credit): bool => $credit->id === $artist->id && $credit->pivot->role === ArtistRole::Remixer->value)) {
                continue;
            }

            $track->artists()->attach($artist->id, ['role' => ArtistRole::Remixer->value, 'position' => $track->artists->count()]);
            $credited[] = "{$artist->name}: {$track->name}";
        }

        return $credited;
    }
}
