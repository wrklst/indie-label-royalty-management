<?php

namespace App\Services\Releases;

use App\Enums\ReleaseFileKind;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\Track;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

    /**
     * The audio file to play for each track, by track id. A recording that
     * is on several releases has one track per release, all with the same
     * ISRC, and its audio is usually uploaded once: a track without audio
     * of its own plays that of another entry of its recording.
     *
     * @param  Collection<int, Track>  $tracks
     * @return Collection<int, ReleaseFile|null>
     */
    public static function forTracks(Collection $tracks): Collection
    {
        $entries = $tracks->concat(Track::query()->whereIn('isrc', $tracks->pluck('isrc')->filter()->unique())->get(['id', 'label_id', 'isrc']))->unique('id');
        $audio = ReleaseFile::query()->where('kind', ReleaseFileKind::Audio)->whereIn('track_id', $entries->pluck('id'))->orderBy('id')->get()->groupBy('track_id');
        $recordings = $entries->whereNotNull('isrc')->groupBy(fn (Track $track): string => $track->label_id.'|'.$track->isrc);

        return $tracks->mapWithKeys(fn (Track $track): array => [
            $track->id => self::pick($audio->get($track->id)) ?? ($track->isrc === null ? null : $recordings[$track->label_id.'|'.$track->isrc]
                ->map(fn (Track $entry): ?ReleaseFile => self::pick($audio->get($entry->id)))->filter()->first()),
        ]);
    }

    /**
     * What the player needs to play each release as an album: its tracks
     * that have audio, in play order.
     *
     * @param  EloquentCollection<int, Release>  $releases
     * @return array<int, list<array{id: int, fileId: int, title: string, artist: string|null, artistId: int|null, release: array{id: int, name: string}, coverFileId: int|null}>>
     */
    public static function albums(EloquentCollection $releases): array
    {
        $releases->loadMissing(['artist:id,name', 'artists:id,name', 'cover:id,release_id', 'tracks.artist:id,name', 'tracks.artists:id,name']);

        $audio = self::forTracks($releases->flatMap(fn (Release $release) => $release->tracks));

        return $releases->mapWithKeys(fn (Release $release): array => [
            $release->id => array_values($release->tracks->sortBy([['disc_number', 'asc'], ['track_number', 'asc'], ['id', 'asc']])
                ->map(fn (Track $track): ?array => ($file = $audio->get($track->id)) === null ? null : [
                    'id' => $track->id,
                    'fileId' => $file->id,
                    'title' => $track->version ? "{$track->name} ({$track->version})" : $track->name,
                    'artist' => ArtistCredits::display($track) ?? ArtistCredits::display($release),
                    'artistId' => $track->artist_id ?? $release->artist_id,
                    'release' => ['id' => $release->id, 'name' => $release->name],
                    'coverFileId' => $release->cover?->id,
                ])->filter()->all()),
        ])->all();
    }
}
