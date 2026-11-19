<?php

use App\Jobs\DetectPlatformLinks;
use App\Models\Artist;
use App\Models\Label;
use App\Models\PlatformLink;
use App\Models\Release;
use App\Models\Track;
use App\Models\Transaction;
use App\Services\Catalog\PlatformLinks;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

const SEARCH_URL = 'https://api.dataforseo.com/v3/serp/google/organic/live/advanced';

/**
 * A release by "Example Artist" called "Example Release" with one track.
 *
 * @return array{label: Label, release: Release, track: Track}
 */
function linkedRelease(array $label = [], array $release = []): array
{
    $label = Label::factory()->create(['airplay_markets' => ['DE'], ...$label]);
    $artist = Artist::factory()->create(['label_id' => $label->id, 'name' => 'Example Artist']);
    $release = Release::factory()->create(['label_id' => $label->id, 'artist_id' => $artist->id, 'name' => 'Example Release', 'upc' => '4000000000001', 'spotify_id' => null, ...$release]);
    $track = Track::factory()->create(['label_id' => $label->id, 'release_id' => $release->id, 'artist_id' => $artist->id, 'name' => 'Opening', 'isrc' => 'ZZTST2600001', 'track_number' => 1]);

    return ['label' => $label, 'release' => $release, 'track' => $track];
}

function searchResults(array $items): array
{
    return ['status_code' => 20000, 'tasks' => [['status_code' => 20000, 'result' => [['items' => array_map(
        fn (array $item): array => ['type' => 'organic', 'description' => null, 'domain' => parse_url($item['url'], PHP_URL_HOST), ...$item],
        $items,
    )]]]]];
}

test('a link is recognised by the platform its address is on', function (string $url, string $platform) {
    expect(PlatformLinks::platformOf($url))->toBe($platform);
})->with([
    ['https://open.spotify.com/album/example', 'spotify'],
    ['https://music.apple.com/de/album/example/1', 'apple_music'],
    ['https://example.bandcamp.com/album/example-release', 'bandcamp'],
    ['https://www.subvert.fm/example-artist/example-release', 'subvert'],
    ['https://music.youtube.com/playlist?list=example', 'youtube_music'],
    ['https://www.youtube.com/watch?v=example', 'youtube'],
    ['https://www.beatport.com/release/example/1', 'beatport'],
    ['https://notbandcamp.com/album/example', 'other'],
    ['https://example.org/release', 'other'],
]);

test('the pages of a release are found by its codes, in its bandcamp sales, on subvert and by one search', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.deezer.com/album/upc:4000000000001' => Http::response(['link' => 'https://www.deezer.com/album/100']),
        'https://itunes.apple.com/lookup?upc=4000000000001' => Http::response(['results' => [['collectionViewUrl' => 'https://music.apple.com/us/album/example-release/200?uo=4']]]),
        'https://www.subvert.fm/example-artist/example-release' => Http::response('<html><title>Example Release by Example Artist</title></html>'),
        SEARCH_URL => Http::response(searchResults([
            ['url' => 'https://tidal.com/browse/album/300', 'title' => 'Example Release by Example Artist on TIDAL'],
            ['url' => 'https://www.beatport.com/release/another/400', 'title' => 'Another Release'],
            ['url' => 'https://blog.example.org/review', 'title' => 'Example Release reviewed'],
            ['url' => 'https://www.deezer.com/album/999', 'title' => 'Example Release'],
        ])),
    ]);
    ['label' => $label, 'release' => $release] = linkedRelease(['dataforseo_login' => 'example-login', 'dataforseo_password' => 'example-password'], ['spotify_id' => 'spotifyExampleId']);
    Transaction::factory()->create([
        'label_id' => $label->id, 'release_id' => $release->id, 'track_id' => null, 'provider' => 'Bandcamp',
        'raw_data' => ['item_url' => 'https://example.bandcamp.com/album/example-release'],
    ]);

    $added = app(PlatformLinks::class)->detectRelease($release);

    expect($added)->toBe(6)
        ->and(PlatformLink::query()->withoutGlobalScope('label')->where('release_id', $release->id)->orderBy('id')->get()->map->only(['label_id', 'platform', 'url', 'origin'])->all())->toBe([
            ['label_id' => $label->id, 'platform' => 'spotify', 'url' => 'https://open.spotify.com/album/spotifyExampleId', 'origin' => 'lookup'],
            ['label_id' => $label->id, 'platform' => 'deezer', 'url' => 'https://www.deezer.com/album/100', 'origin' => 'lookup'],
            ['label_id' => $label->id, 'platform' => 'apple_music', 'url' => 'https://music.apple.com/us/album/example-release/200', 'origin' => 'lookup'],
            ['label_id' => $label->id, 'platform' => 'bandcamp', 'url' => 'https://example.bandcamp.com/album/example-release', 'origin' => 'sales'],
            ['label_id' => $label->id, 'platform' => 'subvert', 'url' => 'https://www.subvert.fm/example-artist/example-release', 'origin' => 'guess'],
            ['label_id' => $label->id, 'platform' => 'tidal', 'url' => 'https://tidal.com/browse/album/300', 'origin' => 'search'],
        ]);
});

test('nothing is searched without a search login, and a lookup that fails does not stop the others', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.deezer.com/*' => Http::response(['error' => ['type' => 'DataException', 'message' => 'no data']]),
        'https://itunes.apple.com/*' => Http::response('unavailable', 503),
        'https://www.subvert.fm/*' => Http::response('<html>Example Release</html>'),
    ]);
    config(['services.dataforseo.login' => null, 'services.dataforseo.password' => null]);
    ['release' => $release] = linkedRelease();

    expect(app(PlatformLinks::class)->detectRelease($release))->toBe(1)
        ->and(PlatformLink::query()->withoutGlobalScope('label')->sole()->platform)->toBe('subvert');
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'dataforseo'));
});

test('a subvert address that exists but is about something else is not kept', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.deezer.com/*' => Http::response([]),
        'https://itunes.apple.com/*' => Http::response(['results' => []]),
        'https://www.subvert.fm/*' => Http::response('<html>Page not found</html>'),
    ]);
    config(['services.dataforseo.login' => null]);
    ['release' => $release] = linkedRelease();

    expect(app(PlatformLinks::class)->detectRelease($release))->toBe(0);
});

test('a track is found by its ISRC and as the song of its album', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.deezer.com/track/isrc:ZZTST2600001' => Http::response(['link' => 'https://www.deezer.com/track/500']),
        'https://itunes.apple.com/lookup?entity=song&upc=4000000000001' => Http::response(['results' => [
            ['wrapperType' => 'collection', 'collectionViewUrl' => 'https://music.apple.com/us/album/example-release/200'],
            ['wrapperType' => 'track', 'trackName' => 'Second Track', 'trackNumber' => 2, 'trackViewUrl' => 'https://music.apple.com/us/album/second-track/200?i=602'],
            ['wrapperType' => 'track', 'trackName' => 'Opening', 'trackNumber' => 1, 'trackViewUrl' => 'https://music.apple.com/us/album/opening/200?i=601&uo=4'],
        ]]),
    ]);
    ['track' => $track] = linkedRelease();

    expect(app(PlatformLinks::class)->detectTrack($track))->toBe(2)
        ->and(PlatformLink::query()->withoutGlobalScope('label')->where('track_id', $track->id)->orderBy('id')->pluck('url')->all())->toBe([
            'https://www.deezer.com/track/500',
            'https://music.apple.com/us/album/opening/200?i=601',
        ]);
});

test('looking again adds nothing twice, keeps the link the user entered and leaves a removed one removed', function () {
    Http::fake([
        'https://api.deezer.com/*' => Http::response(['link' => 'https://www.deezer.com/album/100']),
        'https://itunes.apple.com/*' => Http::response(['results' => [['collectionViewUrl' => 'https://music.apple.com/us/album/example-release/200']]]),
        'https://www.subvert.fm/*' => Http::response('nothing here', 404),
    ]);
    config(['services.dataforseo.login' => null]);
    ['label' => $label, 'release' => $release] = linkedRelease();
    PlatformLink::factory()->create(['label_id' => $label->id, 'release_id' => $release->id, 'platform' => 'deezer', 'url' => 'https://www.deezer.com/album/777']);
    PlatformLink::factory()->create([
        'label_id' => $label->id, 'release_id' => $release->id, 'platform' => 'apple_music', 'url' => 'https://music.apple.com/us/album/example-release/200',
        'origin' => 'lookup', 'dismissed_at' => now(),
    ]);

    expect(app(PlatformLinks::class)->detectRelease($release))->toBe(0)
        ->and(PlatformLink::query()->withoutGlobalScope('label')->count())->toBe(2);
});

test('finding links is queued and the page says it is looking', function () {
    Queue::fake();
    ['label' => $label, 'release' => $release] = linkedRelease();
    $this->actingAs(labelAdmin($label));

    $this->post(route('platform-links.detect'), ['release_id' => $release->id])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Looking for links. They appear here as they are found.');

    Queue::assertPushed(DetectPlatformLinks::class, fn (DetectPlatformLinks $job): bool => $job->releaseId === $release->id && $job->trackId === null);
    $this->get(route('releases.show', $release))->assertInertia(fn (Assert $page) => $page->where('platformLinks.detecting', true));
});

test('the job looks the release and its tracks up and then stops saying it is looking', function () {
    Http::fake([
        'https://api.deezer.com/album/*' => Http::response(['link' => 'https://www.deezer.com/album/100']),
        'https://api.deezer.com/track/*' => Http::response(['link' => 'https://www.deezer.com/track/500']),
        '*' => Http::response([], 404),
    ]);
    config(['services.dataforseo.login' => null]);
    ['release' => $release, 'track' => $track] = linkedRelease();
    PlatformLinks::mark($release, true);

    (new DetectPlatformLinks($release->id))->handle(app(PlatformLinks::class));

    expect(PlatformLinks::isDetecting($release))->toBeFalse()
        ->and(PlatformLink::query()->withoutGlobalScope('label')->where('release_id', $release->id)->value('url'))->toBe('https://www.deezer.com/album/100')
        ->and(PlatformLink::query()->withoutGlobalScope('label')->where('track_id', $track->id)->value('url'))->toBe('https://www.deezer.com/track/500');
});

test('a link is added by hand with its platform recognised, corrected and removed', function () {
    ['label' => $label, 'release' => $release, 'track' => $track] = linkedRelease();
    $this->actingAs(labelAdmin($label));

    $this->post(route('platform-links.store'), ['track_id' => $track->id, 'url' => 'https://example.bandcamp.com/track/opening'])->assertSessionHasNoErrors();
    $link = PlatformLink::query()->sole();

    expect($link->only(['track_id', 'release_id', 'platform', 'origin']))->toBe(['track_id' => $track->id, 'release_id' => null, 'platform' => 'bandcamp', 'origin' => 'manual']);

    $this->put(route('platform-links.update', $link), ['url' => 'https://shop.example.org/opening', 'platform' => 'other'])->assertSessionHasNoErrors();
    expect($link->refresh()->only(['url', 'platform']))->toBe(['url' => 'https://shop.example.org/opening', 'platform' => 'other']);

    $this->get(route('tracks.show', $track->id))->assertInertia(fn (Assert $page) => $page
        ->where('platformLinks.links.0.platform_name', 'Other')
        ->where('platformLinks.links.0.url', 'https://shop.example.org/opening')
        ->where('platformLinks.detecting', false));

    $this->delete(route('platform-links.destroy', $link))->assertSessionHasNoErrors();
    expect(PlatformLink::query()->count())->toBe(0);
});

test('a found link that is removed is kept out of sight and comes back when added by hand', function () {
    ['label' => $label, 'release' => $release] = linkedRelease();
    $found = PlatformLink::factory()->create(['label_id' => $label->id, 'release_id' => $release->id, 'platform' => 'tidal', 'url' => 'https://tidal.com/browse/album/300', 'origin' => 'search']);
    $this->actingAs(labelAdmin($label));

    $this->delete(route('platform-links.destroy', $found))->assertSessionHasNoErrors();

    expect($found->refresh()->dismissed_at)->not->toBeNull();
    $this->get(route('releases.show', $release))->assertInertia(fn (Assert $page) => $page->has('platformLinks.links', 0));

    $this->post(route('platform-links.store'), ['release_id' => $release->id, 'url' => 'https://tidal.com/browse/album/300'])->assertSessionHasNoErrors();

    expect(PlatformLink::query()->sole()->only(['origin', 'dismissed_at']))->toBe(['origin' => 'manual', 'dismissed_at' => null]);
});

test('a link that cannot be stored is refused', function (Closure $input, string $field) {
    ['label' => $label, 'release' => $release] = linkedRelease();
    PlatformLink::factory()->create(['label_id' => $label->id, 'release_id' => $release->id, 'url' => 'https://example.bandcamp.com/album/example-release']);
    $before = PlatformLink::query()->withoutGlobalScope('label')->count();

    $this->actingAs(labelAdmin($label))
        ->post(route('platform-links.store'), $input($release))
        ->assertSessionHasErrors($field);

    expect(PlatformLink::query()->withoutGlobalScope('label')->count())->toBe($before);
})->with([
    'no address' => [fn (Release $release) => ['release_id' => $release->id, 'url' => 'bandcamp'], 'url'],
    'a script address' => [fn (Release $release) => ['release_id' => $release->id, 'url' => 'javascript:alert(1)'], 'url'],
    'the same link again' => [fn (Release $release) => ['release_id' => $release->id, 'url' => 'https://example.bandcamp.com/album/example-release'], 'url'],
    'an unknown platform' => [fn (Release $release) => ['release_id' => $release->id, 'url' => 'https://example.org/a', 'platform' => 'myspace'], 'platform'],
    'no release or track' => [fn (Release $release) => ['url' => 'https://example.org/a'], 'release_id'],
    'a release of another label' => [fn (Release $release) => ['release_id' => Release::factory()->create()->id, 'url' => 'https://example.org/a'], 'release_id'],
]);

test('links of another label cannot be changed, removed or looked up', function () {
    ['label' => $label, 'release' => $release] = linkedRelease();
    $link = PlatformLink::factory()->create(['label_id' => $label->id, 'release_id' => $release->id]);
    Queue::fake();
    $this->actingAs(labelAdmin(Label::factory()->create()));

    $this->put(route('platform-links.update', $link), ['url' => 'https://example.org/changed'])->assertNotFound();
    $this->delete(route('platform-links.destroy', $link))->assertNotFound();
    $this->post(route('platform-links.detect'), ['release_id' => $release->id])->assertSessionHasErrors('release_id');

    expect($link->refresh()->url)->toBe('https://example.bandcamp.com/album/example-release');
    Queue::assertNothingPushed();
});
