<?php

namespace App\Services\Catalog;

use App\Models\Label;
use App\Models\PlatformLink;
use App\Models\Release;
use App\Models\Track;
use App\Models\Transaction;
use App\Services\Airplay\WebSearch;
use App\Services\Releases\ArtistCredits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Finds where a release or a track is offered. Four sources, each
 * optional and none of them able to break the others:
 *
 * - lookup: Deezer and Apple Music answer for a UPC or an ISRC, and a
 *   stored Spotify ID is a link already;
 * - sales: Bandcamp sales name the page of the item sold;
 * - guess: Subvert pages are named after artist and release, so the
 *   address is tried and kept when the page names the release;
 * - search: with a web search login, one search whose results on known
 *   platforms are kept when they name the release.
 */
class PlatformLinks
{
    /**
     * Platforms by key: their name and the hosts their pages are on.
     *
     * @var array<string, array{name: string, hosts: list<string>}>
     */
    public const array PLATFORMS = [
        'spotify' => ['name' => 'Spotify', 'hosts' => ['open.spotify.com']],
        'apple_music' => ['name' => 'Apple Music', 'hosts' => ['music.apple.com', 'itunes.apple.com']],
        'bandcamp' => ['name' => 'Bandcamp', 'hosts' => ['bandcamp.com']],
        'subvert' => ['name' => 'Subvert', 'hosts' => ['subvert.fm']],
        'deezer' => ['name' => 'Deezer', 'hosts' => ['deezer.com', 'deezer.page.link']],
        'tidal' => ['name' => 'Tidal', 'hosts' => ['tidal.com']],
        'youtube_music' => ['name' => 'YouTube Music', 'hosts' => ['music.youtube.com']],
        'youtube' => ['name' => 'YouTube', 'hosts' => ['youtube.com', 'youtu.be']],
        'amazon_music' => ['name' => 'Amazon Music', 'hosts' => ['music.amazon.com', 'music.amazon.de', 'music.amazon.co.uk', 'amazon.com', 'amazon.de', 'amazon.co.uk']],
        'soundcloud' => ['name' => 'SoundCloud', 'hosts' => ['soundcloud.com']],
        'beatport' => ['name' => 'Beatport', 'hosts' => ['beatport.com']],
        'qobuz' => ['name' => 'Qobuz', 'hosts' => ['qobuz.com']],
        'juno' => ['name' => 'Juno Download', 'hosts' => ['junodownload.com', 'juno.co.uk']],
        'boomkat' => ['name' => 'Boomkat', 'hosts' => ['boomkat.com']],
        'bleep' => ['name' => 'Bleep', 'hosts' => ['bleep.com']],
        'traxsource' => ['name' => 'Traxsource', 'hosts' => ['traxsource.com']],
        'discogs' => ['name' => 'Discogs', 'hosts' => ['discogs.com']],
        'pandora' => ['name' => 'Pandora', 'hosts' => ['pandora.com']],
        'napster' => ['name' => 'Napster', 'hosts' => ['napster.com']],
        'anghami' => ['name' => 'Anghami', 'hosts' => ['anghami.com']],
        'songlink' => ['name' => 'Smart link', 'hosts' => ['song.link', 'album.link', 'odesli.co', 'lnk.to', 'ffm.to', 'fanlink.to', 'linktr.ee']],
    ];

    public const string OTHER = 'other';

    private const int SEARCH_DEPTH = 30;

    public function __construct(private readonly WebSearch $search) {}

    /**
     * The platform a link belongs to, by its host.
     */
    public static function platformOf(string $url): string
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $host = Str::startsWith($host, 'www.') ? substr($host, 4) : $host;

        foreach (self::PLATFORMS as $key => $platform) {
            foreach ($platform['hosts'] as $known) {
                if ($host === $known || Str::endsWith($host, '.'.$known)) {
                    return $key;
                }
            }
        }

        return self::OTHER;
    }

    public static function nameOf(string $platform): string
    {
        return self::PLATFORMS[$platform]['name'] ?? 'Other';
    }

    public static function isDetecting(Release|Track $record): bool
    {
        return Cache::has(self::key($record));
    }

    /**
     * Mark a record as being looked up, for a few minutes at most.
     */
    public static function mark(Release|Track $record, bool $detecting): void
    {
        $detecting ? Cache::put(self::key($record), true, now()->addMinutes(10)) : Cache::forget(self::key($record));
    }

    /**
     * Look for the pages of a release and store the new ones.
     *
     * @return int How many links were added.
     */
    public function detectRelease(Release $release): int
    {
        $release->loadMissing(['artist:id,name', 'artists:id,name']);
        $artist = ArtistCredits::display($release);
        $found = [];

        if (filled($release->spotify_id)) {
            $found[] = ['https://open.spotify.com/album/'.rawurlencode((string) $release->spotify_id), 'lookup'];
        }

        if (filled($release->upc)) {
            $found[] = [$this->json('https://api.deezer.com/album/upc:'.rawurlencode((string) $release->upc))['link'] ?? null, 'lookup'];
            $found[] = [$this->appleAlbum((string) $release->upc)['collectionViewUrl'] ?? null, 'lookup'];
        }

        $found[] = [$this->soldAt(Transaction::query()->withoutGlobalScope('label')->where('release_id', $release->id)->whereNull('track_id')), 'sales'];
        $found[] = [$this->subvertPage($artist, $release->name), 'guess'];

        foreach ($this->searched($release->label_id, $artist, $release->name) as $url) {
            $found[] = [$url, 'search'];
        }

        return $this->store($release, $found);
    }

    /**
     * Look for the pages of a track and store the new ones.
     *
     * @return int How many links were added.
     */
    public function detectTrack(Track $track): int
    {
        $track->loadMissing(['release:id,upc,name']);
        $found = [];

        if (filled($track->isrc)) {
            $found[] = [$this->json('https://api.deezer.com/track/isrc:'.rawurlencode((string) $track->isrc))['link'] ?? null, 'lookup'];
        }

        if (filled($track->release?->upc)) {
            $found[] = [$this->appleTrack((string) $track->release->upc, $track), 'lookup'];
        }

        $found[] = [$this->soldAt(Transaction::query()->withoutGlobalScope('label')->where('track_id', $track->id)), 'sales'];

        return $this->store($track, $found);
    }

    /**
     * Add a link unless the record has it already, shown or dismissed.
     *
     * @param  list<array{string|null, string}>  $found  Address and how it was found.
     */
    private function store(Release|Track $record, array $found): int
    {
        $column = $record instanceof Release ? 'release_id' : 'track_id';
        $existing = PlatformLink::query()->withoutGlobalScope('label')->where($column, $record->id)->get(['url', 'platform', 'dismissed_at']);
        $known = $existing->map(fn (PlatformLink $link): string => self::comparable($link->url))->all();
        // One link per platform is found; further ones are for the user to add.
        $platforms = $existing->whereNull('dismissed_at')->pluck('platform')->all();
        $added = 0;

        foreach ($found as [$url, $origin]) {
            if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false || ! Str::startsWith($url, 'https://')) {
                continue;
            }

            $url = self::clean($url);
            $platform = self::platformOf($url);

            if ($platform === self::OTHER || in_array(self::comparable($url), $known, true) || in_array($platform, $platforms, true)) {
                continue;
            }

            PlatformLink::query()->create(['label_id' => $record->label_id, $column => $record->id, 'platform' => $platform, 'url' => mb_substr($url, 0, 1000), 'origin' => $origin]);
            $known[] = self::comparable($url);
            $platforms[] = $platform;
            $added++;
        }

        return $added;
    }

    /**
     * The page a Bandcamp sale of these transactions names, the newest first.
     *
     * @param  Builder<Transaction>  $transactions
     */
    private function soldAt($transactions): ?string
    {
        $raw = $transactions->where('provider', 'Bandcamp')->whereNotNull('raw_data')->orderByDesc('id')->limit(25)->pluck('raw_data');

        foreach ($raw as $line) {
            $url = is_array($line) ? ($line['item_url'] ?? $line['item url'] ?? null) : null;

            if (is_string($url) && $url !== '') {
                return Str::startsWith($url, 'http') ? $url : 'https://'.$url;
            }
        }

        return null;
    }

    /**
     * Subvert names a page after artist and release. The guess counts
     * only when the page exists and names the release.
     */
    private function subvertPage(?string $artist, string $name): ?string
    {
        if (blank($artist) || Str::slug($artist) === '' || Str::slug($name) === '') {
            return null;
        }

        $url = 'https://www.subvert.fm/'.Str::slug($artist).'/'.Str::slug($name);

        try {
            $response = Http::withUserAgent('Mozilla/5.0 (compatible; platform-links)')->connectTimeout(5)->timeout(15)->get($url);
        } catch (Throwable) {
            return null;
        }

        return $response->successful() && Str::contains(Str::lower($response->body()), Str::lower(e($name))) ? $url : null;
    }

    /**
     * Results of one web search that are on a known platform and name
     * the release. Nothing when the label has no search login.
     *
     * @return list<string>
     */
    private function searched(int $labelId, ?string $artist, string $name): array
    {
        $label = Label::query()->find($labelId);

        if ($label === null || ! $this->search->for($label)->isConfigured()) {
            return [];
        }

        try {
            $results = $this->search->search(trim(($artist === null ? '' : "\"{$artist}\" ")."\"{$name}\""), $label->airplayMarkets()[0], self::SEARCH_DEPTH);
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }

        $needle = self::plain($name);

        $urls = [];

        foreach ($results as $result) {
            if (self::platformOf($result['url']) !== self::OTHER && Str::contains(self::plain($result['title'].' '.($result['description'] ?? '')), $needle)) {
                $urls[] = $result['url'];
            }
        }

        return $urls;
    }

    /**
     * @return array<string, mixed>
     */
    private function appleAlbum(string $upc): array
    {
        $album = $this->json('https://itunes.apple.com/lookup?upc='.rawurlencode($upc))['results'][0] ?? [];

        return is_array($album) ? $album : [];
    }

    /**
     * The track's own page on Apple Music: the song of the album with
     * the same number, or failing that the same name.
     */
    private function appleTrack(string $upc, Track $track): ?string
    {
        $results = $this->json('https://itunes.apple.com/lookup?entity=song&upc='.rawurlencode($upc))['results'] ?? [];
        $byName = null;
        $byNumber = null;

        foreach (is_array($results) ? $results : [] as $entry) {
            if (! is_array($entry) || ($entry['wrapperType'] ?? null) !== 'track') {
                continue;
            }

            $name = self::plain((string) ($entry['trackName'] ?? ''));

            if ($name === self::plain($track->name)) {
                $byName ??= $entry;
            } elseif ($track->track_number !== null
                && (int) ($entry['trackNumber'] ?? 0) === (int) $track->track_number
                && (int) ($entry['discNumber'] ?? 1) === (int) ($track->disc_number ?? 1)
                && Str::contains($name, self::plain($track->name))) {
                $byNumber ??= $entry;
            }
        }

        $song = $byName ?? $byNumber ?? [];

        return is_string($song['trackViewUrl'] ?? null) ? $song['trackViewUrl'] : null;
    }

    /**
     * A public lookup that needs no key. Anything but a JSON answer is nothing found.
     *
     * @return array<string, mixed>
     */
    private function json(string $url): array
    {
        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(15)->get($url);
        } catch (Throwable) {
            return [];
        }

        $data = $response->successful() ? $response->json() : null;

        return is_array($data) ? $data : [];
    }

    /**
     * Without the tracking additions shops put on their links.
     */
    private static function clean(string $url): string
    {
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $kept = array_diff_key($query, array_flip(['uo', 'si', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'app', 'ref']));

        return Str::before($url, '?').($kept === [] ? '' : '?'.http_build_query($kept));
    }

    private static function comparable(string $url): string
    {
        return Str::lower(rtrim(preg_replace('#^https?://(www\.)?#i', '', self::clean($url)) ?? $url, '/'));
    }

    private static function plain(string $text): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', Str::lower($text)));
    }

    private static function key(Release|Track $record): string
    {
        return 'platform-links:'.($record instanceof Release ? 'release' : 'track').':'.$record->id;
    }
}
