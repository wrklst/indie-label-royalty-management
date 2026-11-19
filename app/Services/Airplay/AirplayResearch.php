<?php

namespace App\Services\Airplay;

use App\Models\Airplay;
use App\Models\AirplayFinding;
use App\Models\AirplaySearch;
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

    /** Every search is billed and takes about half a minute, so known plays get few of their own. */
    private const int MAX_AIRPLAY_SEARCHES = 2;

    /** Seconds after which no further search is started, to stay inside the queue job's time limit. */
    private const int TIME_BUDGET = 420;

    /**
     * Broadcasters that publish what they played on pages Google indexes, by country.
     *
     * @var array<string, string>
     */
    private const array BROADCASTERS = ['GB' => 'BBC'];

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
    private const string RADIO_WORDS = '/\b(radio|playlists?|tracklists?|airplay|sendung|gespielt|fm|broadcast|charts?|bbc|nts|kexp|kcrw|npr|rinse)\b/iu';

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
     * Search for the track and store what is new: in every configured
     * country, for the broadcasters known to list what they play, for each
     * station a statement named, and in the country of each play whose
     * station is unknown.
     *
     * @return int The number of new findings.
     *
     * @throws RuntimeException when no search could be run; the track then stays due
     */
    public function research(Track $track): int
    {
        $track->loadMissing(['artist:id,name', 'artists:id,name', 'release.artist:id,name', 'release.artists:id,name']);

        $artist = ArtistCredits::display($track) ?? ($track->release ? ArtistCredits::display($track->release) : null);
        $subject = collect([$artist, $track->name])->filter()->map(fn (string $part): string => "\"{$part}\"")->implode(' ');
        $markets = $this->search->markets();

        $airplays = Airplay::query()->withoutGlobalScope('label')->where('label_id', $track->label_id)->get(['id', 'track_id', 'station', 'region']);
        $stations = $airplays->pluck('station')->filter()->unique(fn (string $station): string => Str::lower($station))->values();
        $own = $airplays->where('track_id', $track->id);

        $searches = collect($markets)
            ->map(fn (string $region): array => ['query' => "{$subject} radio playlist", 'region' => $region, 'depth' => self::GENERAL_DEPTH, 'airplay' => null])
            ->concat(collect(self::BROADCASTERS)->only($markets)
                ->map(fn (string $broadcaster, string $region): array => ['query' => "{$subject} {$broadcaster}", 'region' => $region, 'depth' => 10, 'airplay' => null])->values())
            ->concat($own->whereNotNull('station')->unique(fn (Airplay $airplay): string => Str::lower($airplay->station))->take(self::MAX_AIRPLAY_SEARCHES)
                ->map(fn (Airplay $airplay): array => ['query' => "{$subject} {$airplay->station}", 'region' => $airplay->region ?? $markets[0], 'depth' => 10, 'airplay' => $airplay]))
            ->concat($own->whereNull('station')->whereNotNull('region')->whereNotIn('region', $markets)->unique('region')->take(self::MAX_AIRPLAY_SEARCHES)
                ->map(fn (Airplay $airplay): array => ['query' => "{$subject} radio playlist", 'region' => $airplay->region ?? $markets[0], 'depth' => self::GENERAL_DEPTH, 'airplay' => $airplay]));

        $found = $this->run($searches, $track->label_id, $track->id, $stations, [$artist, $track->name]);

        // ponytail: researched once any search worked; a country that failed is tried again with the next research.
        $track->forceFill(['researched_at' => now()])->save();

        return $found;
    }

    /**
     * Look closer at one play: which show it was in, and whether the
     * station or anyone else wrote about it. Findings are tied to the play.
     *
     * @return int The number of new findings.
     *
     * @throws RuntimeException when no search could be run
     */
    public function researchAirplay(Airplay $airplay): int
    {
        $region = $airplay->region ?? $this->search->markets()[0];
        $artist = $airplay->artist_name === null ? null : "\"{$airplay->artist_name}\"";
        $title = "\"{$airplay->track_name}\"";
        $station = $airplay->station;

        $queries = $station === null
            ? [trim("{$artist} {$title} radio playlist"), trim("{$artist} {$title} radio show tracklist")]
            : [trim("{$artist} {$title} {$station}"), trim("{$artist} {$station} playlist"), "{$title} {$station} playlist"];

        $searches = collect($queries)->unique()->values()
            ->map(fn (string $query, int $index): array => ['query' => $query, 'region' => $region, 'depth' => $index === 0 ? self::GENERAL_DEPTH : 10, 'airplay' => $airplay]);

        return $this->run($searches, $airplay->label_id, $airplay->track_id, collect($station === null ? [] : [$station]), [$airplay->artist_name, $airplay->track_name]);
    }

    /**
     * Run the searches and store what is new.
     *
     * @param  iterable<int, array{query: string, region: string, depth: int, airplay: Airplay|null}>  $searches
     * @param  Collection<int, string>  $stations  Stations the label already knows.
     * @param  list<string|null>  $names  Artist and title; a page has to mention one of them.
     *
     * @throws RuntimeException when no search could be run
     */
    private function run(iterable $searches, int $labelId, ?int $trackId, Collection $stations, array $names): int
    {
        $found = 0;
        $succeeded = 0;
        $failure = null;
        $deadline = microtime(true) + self::TIME_BUDGET;

        foreach ($searches as $search) {
            if (microtime(true) > $deadline) {
                break;
            }

            $log = ['label_id' => $labelId, 'track_id' => $trackId, 'airplay_id' => $search['airplay']?->id, 'query' => $search['query'], 'region' => $search['region']];

            try {
                $results = $this->search->search($search['query'], $search['depth'], $search['region']);
            } catch (RuntimeException $exception) {
                // The search service fails often; one failed search should not lose the others.
                $failure = $exception;
                AirplaySearch::query()->withoutGlobalScope('label')->create([...$log, 'succeeded' => false, 'error' => mb_substr($exception->getMessage(), 0, 500)]);

                continue;
            }

            $succeeded++;
            $new = 0;

            foreach ($results as $result) {
                $new += $this->store($labelId, $trackId, $result, $search['query'], $search['airplay'], $stations, $names) ? 1 : 0;
            }

            $found += $new;
            AirplaySearch::query()->withoutGlobalScope('label')->create([...$log, 'succeeded' => true, 'new_findings' => $new, 'results' => $results]);
        }

        if ($succeeded === 0) {
            throw $failure ?? new RuntimeException('No search could be run.');
        }

        return $found;
    }

    /**
     * @param  array{title: string, url: string, description: string|null, domain: string|null}  $result
     * @param  Collection<int, string>  $stations  Stations the label already knows.
     * @param  list<string|null>  $names  Artist and title; a page has to mention one of them.
     */
    private function store(int $labelId, ?int $trackId, array $result, string $query, ?Airplay $airplay, Collection $stations, array $names): bool
    {
        $domain = Str::lower((string) ($result['domain'] ?? parse_url($result['url'], PHP_URL_HOST)));

        if (array_any(self::IGNORED_DOMAINS, fn (string $ignored): bool => $domain === $ignored || str_ends_with($domain, ".{$ignored}"))) {
            return false;
        }

        $text = Str::lower("{$result['title']} {$result['description']} {$domain}");

        // Google also returns pages that only share a word with the title; they say nothing about this track.
        if (! array_any(array_filter($names), fn (string $name): bool => str_contains($text, Str::lower($name)))) {
            return false;
        }
        $station = $airplay->station ?? $stations->first(fn (string $station): bool => str_contains($text, Str::lower($station)));

        return AirplayFinding::query()->withoutGlobalScope('label')->firstOrCreate(
            ['label_id' => $labelId, 'track_id' => $trackId, 'url_hash' => sha1($result['url'])],
            [
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
