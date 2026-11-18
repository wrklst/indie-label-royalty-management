<?php

namespace App\Services\Releases;

use App\Enums\ArtistRole;
use App\Models\Release;
use App\Models\Track;

/**
 * The metadata of releases as a flat table, one line per track, in the
 * shape distributors and stores ask for.
 */
class ReleaseMetadataExport
{
    /** @var list<string> */
    public const array COLUMNS = [
        'catalog_number', 'upc', 'release_title', 'release_version', 'release_artist', 'release_type', 'release_date',
        'original_release_date', 'preorder_date', 'label', 'genre', 'subgenre', 'language', 'explicit', 'p_line', 'c_line',
        'status', 'blocked_services', 'blocked_territories', 'disc_number', 'track_number', 'track_title', 'track_version',
        'track_artist', 'featuring', 'remixers', 'isrc', 'iswc', 'duration', 'duration_seconds', 'track_explicit', 'track_language',
        'track_genre', 'bpm', 'key', 'composers', 'lyricists', 'producers', 'publishers', 'track_p_line', 'preview_start_seconds',
    ];

    /**
     * @return list<array<string, string|int|null>>
     */
    public function rows(Release $release): array
    {
        $release->loadMissing(['artist', 'artists', 'label', 'tracks.artist', 'tracks.artists']);

        $shared = [
            'catalog_number' => $release->catalog_number,
            'upc' => $release->upc,
            'release_title' => $release->name,
            'release_version' => $release->version,
            'release_artist' => ArtistCredits::display($release),
            'release_type' => $release->release_type?->label(),
            'release_date' => $release->released_on?->toDateString(),
            'original_release_date' => $release->original_released_on?->toDateString(),
            'preorder_date' => $release->preorder_on?->toDateString(),
            'label' => $release->label->name,
            'genre' => $release->genre,
            'subgenre' => $release->subgenre,
            'language' => $release->language,
            'explicit' => $release->explicit,
            'p_line' => $release->p_line,
            'c_line' => $release->c_line,
            'status' => $release->status->label(),
            'blocked_services' => implode(', ', $release->blocked_services ?? []),
            'blocked_territories' => implode(', ', $release->blocked_territories ?? []),
        ];

        $tracks = $release->tracks->sortBy([['disc_number', 'asc'], ['track_number', 'asc'], ['id', 'asc']])->values();

        if ($tracks->isEmpty()) {
            return [[...array_fill_keys(self::COLUMNS, null), ...$shared]];
        }

        return array_values($tracks->map(fn (Track $track): array => [
            ...array_fill_keys(self::COLUMNS, null),
            ...$shared,
            'disc_number' => $track->disc_number,
            'track_number' => $track->track_number,
            'track_title' => $track->name,
            'track_version' => $track->version,
            'track_artist' => implode(' & ', ArtistCredits::names($track, ArtistRole::Primary)) ?: $shared['release_artist'],
            'featuring' => implode(', ', ArtistCredits::names($track, ArtistRole::Featuring)),
            'remixers' => implode(', ', array_unique([...ArtistCredits::names($track, ArtistRole::Remixer), ...($track->credits['remixers'] ?? [])])),
            'isrc' => $track->isrc,
            'iswc' => $track->iswc,
            'duration' => $track->duration === null ? null : sprintf('%d:%02d', intdiv($track->duration, 60), $track->duration % 60),
            'duration_seconds' => $track->duration,
            'track_explicit' => $track->explicit ?? $release->explicit,
            'track_language' => $track->language ?? $release->language,
            'track_genre' => $track->genre ?? $release->genre,
            'bpm' => $track->bpm === null ? null : rtrim(rtrim((string) $track->bpm, '0'), '.'),
            'key' => $track->musical_key,
            'composers' => implode(', ', $track->credits['composers'] ?? []),
            'lyricists' => implode(', ', $track->credits['lyricists'] ?? []),
            'producers' => implode(', ', array_unique([...ArtistCredits::names($track, ArtistRole::Producer), ...($track->credits['producers'] ?? [])])),
            'publishers' => implode(', ', $track->credits['publishers'] ?? []),
            'track_p_line' => $track->p_line ?? $release->p_line,
            'preview_start_seconds' => $track->preview_start,
        ])->all());
    }

    /**
     * Write rows as CSV. Text that a spreadsheet would run as a formula
     * is neutralised.
     *
     * @param  resource  $stream
     * @param  iterable<array<string, string|int|null>>  $rows
     */
    public function write($stream, iterable $rows): void
    {
        fputcsv($stream, self::COLUMNS, ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($stream, array_map(
                fn (string|int|null $cell): string|int|null => is_string($cell) && ! is_numeric($cell) && preg_match('/^[=+\-@\t\r]/', $cell) ? "'".$cell : $cell,
                array_values($row),
            ), ',', '"', '');
        }
    }
}
