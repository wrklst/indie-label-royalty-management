<?php

namespace App\Services\Airplay;

use App\Models\Airplay;
use App\Models\AirplayFinding;
use App\Models\Track;
use App\Services\Releases\ArtistCredits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Looks a track up on the web and keeps the pages that may show where
 * it was played. Nothing becomes an airplay by itself: findings wait for
 * someone to keep or dismiss them.
 */
class AirplayResearch
{
    /** A track is due again when its last research is older than this. */
    public const int STALE_DAYS = 31;

    /** Every search is billed and takes about half a minute, so a track gets few of them. */
    private const int MAX_STATION_SEARCHES = 2;

    /**
     * Shops, streaming services and lyric sites list a track without anyone having played it.
     *
     * @var list<string>
     */
    private const array IGNORED_DOMAINS = [
        'spotify.com', 'apple.com', 'youtube.com', 'youtu.be', 'deezer.com', 'tidal.com', 'amazon.com', 'amazon.de', 'bandcamp.com',
        'beatport.com', 'traxsource.com', 'junodownload.com', 'juno.co.uk', 'soundcloud.com', 'shazam.com', 'genius.com', 'zvuk.com',
        'music.yandex.ru', 'boomplay.com', 'qobuz.com', 'napster.com', 'pandora.com', 'audiomack.com', 'anghami.com', 'jiosaavn.com',
        'discogs.com', 'musicbrainz.org', 'last.fm', 'tiktok.com', 'instagram.com', 'facebook.com', 'x.com', 'viberate.com', 'freefy.app',
        'rateyourmusic.com',
    ];

    /** The first results are streaming services; plays and mentions come after them. Every ten results are billed. */
    private const int GENERAL_DEPTH = 30;

    // ponytail: a word list, not a classifier; findings are reviewed by a person anyway.
    private const string RADIO_WORDS = '/\b(radio|playlists?|tracklists?|airplay|sendung|gespielt|fm|broadcast|charts?)\b/iu';

    public function __construct(private readonly WebSearch $search) {}

    /**
     * The tracks of the current label a research may be started for.
     *
     * @param  array{scope: string, release_id?: int|null, track_ids?: list<int>|null}  $selection
     * @return Builder<Track>
     */
    public function tracks(array $selection): Builder
    {
        return Track::query()->when(true, fn (Builder $query) => match ($selection['scope']) {
            'release' => $query->where('release_id', $selection['release_id'] ?? 0),
            'tracks' => $query->whereIn('id', $selection['track_ids'] ?? []),
            'stale' => $this->stale($query),
            default => $query,
        });
    }

    /**
     * @param  Builder<Track>  $query
     * @return Builder<Track>
     */
    public function stale(Builder $query, int $days = self::STALE_DAYS): Builder
    {
        return $query->where(fn (Builder $query) => $query->whereNull('researched_at')->orWhere('researched_at', '<', now()->subDays($days)));
    }

    /**
     * Search for the track and store what is new.
     *
     * @return int The number of new findings.
     *
     * @throws RuntimeException when a search could not be run; the track then stays due
     */
    public function research(Track $track): int
    {
        $track->loadMissing(['artist:id,name', 'artists:id,name', 'release.artist:id,name', 'release.artists:id,name']);

        $artist = ArtistCredits::display($track) ?? ($track->release ? ArtistCredits::display($track->release) : null);
        $subject = collect([$artist, $track->name])->filter()->map(fn (string $part): string => "\"{$part}\"")->implode(' ');

        $airplays = Airplay::query()->withoutGlobalScope('label')->where('label_id', $track->label_id)->get(['id', 'track_id', 'station']);
        $stations = $airplays->pluck('station')->unique(fn (string $station): string => Str::lower($station))->values();

        $searches = $airplays->where('track_id', $track->id)->unique(fn (Airplay $airplay): string => Str::lower($airplay->station))
            ->take(self::MAX_STATION_SEARCHES)
            ->map(fn (Airplay $airplay): array => ['query' => "{$subject} {$airplay->station}", 'airplay' => $airplay, 'depth' => 10])
            ->prepend(['query' => "{$subject} radio playlist", 'airplay' => null, 'depth' => self::GENERAL_DEPTH])->values();

        $found = 0;

        foreach ($searches as $search) {
            foreach ($this->search->search($search['query'], $search['depth']) as $result) {
                $found += $this->store($track, $result, $search['query'], $search['airplay'], $stations) ? 1 : 0;
            }
        }

        $track->forceFill(['researched_at' => now()])->save();

        return $found;
    }

    /**
     * @param  array{title: string, url: string, description: string|null, domain: string|null}  $result
     * @param  Collection<int, string>  $stations  Stations the label already knows.
     */
    private function store(Track $track, array $result, string $query, ?Airplay $airplay, Collection $stations): bool
    {
        $domain = Str::lower((string) ($result['domain'] ?? parse_url($result['url'], PHP_URL_HOST)));

        if (array_any(self::IGNORED_DOMAINS, fn (string $ignored): bool => $domain === $ignored || str_ends_with($domain, ".{$ignored}"))) {
            return false;
        }

        $text = Str::lower("{$result['title']} {$result['description']} {$domain}");
        $station = $airplay?->station ?? $stations->first(fn (string $station): bool => str_contains($text, Str::lower($station)));

        return AirplayFinding::query()->withoutGlobalScope('label')->firstOrCreate(
            ['track_id' => $track->id, 'url_hash' => sha1($result['url'])],
            [
                'label_id' => $track->label_id,
                'airplay_id' => $airplay?->id,
                'is_radio' => $station !== null || preg_match(self::RADIO_WORDS, $text) === 1,
                'station' => $station,
                'url' => mb_substr($result['url'], 0, 2000),
                'title' => mb_substr($result['title'], 0, 500),
                'description' => $result['description'],
                'domain' => $domain ?: null,
                'query' => $query,
            ],
        )->wasRecentlyCreated;
    }
}
