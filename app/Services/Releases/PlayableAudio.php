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
     * What the player needs to play each release as an album: its tracks
     * that have audio, in play order.
     *
     * @param  EloquentCollection<int, Release>  $releases
     * @return array<int, list<array{id: int, fileId: int, title: string, artist: string|null, artistId: int|null, release: array{id: int, name: string}, coverFileId: int|null}>>
     */
    public static function albums(EloquentCollection $releases): array
    {
        $releases->loadMissing(['artist:id,name', 'artists:id,name', 'cover:id,release_id', 'tracks.artist:id,name', 'tracks.artists:id,name']);

        $audio = ReleaseFile::query()->whereIn('release_id', $releases->modelKeys())->where('kind', ReleaseFileKind::Audio)
            ->whereNotNull('track_id')->orderBy('id')->get()->groupBy('track_id');

        return $releases->mapWithKeys(fn (Release $release): array => [
            $release->id => $release->tracks->sortBy([['disc_number', 'asc'], ['track_number', 'asc'], ['id', 'asc']])
                ->map(fn (Track $track): ?array => ($file = self::pick($audio->get($track->id))) === null ? null : [
                    'id' => $track->id,
                    'fileId' => $file->id,
                    'title' => $track->version ? "{$track->name} ({$track->version})" : $track->name,
                    'artist' => ArtistCredits::display($track) ?? ArtistCredits::display($release),
                    'artistId' => $track->artist_id ?? $release->artist_id,
                    'release' => ['id' => $release->id, 'name' => $release->name],
                    'coverFileId' => $release->cover?->id,
                ])->filter()->values()->all(),
        ])->all();
    }
}
