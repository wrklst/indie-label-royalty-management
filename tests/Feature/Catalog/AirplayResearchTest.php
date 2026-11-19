<?php

use App\Enums\AirplayOrigin;
use App\Jobs\ResearchAirplayEntry;
use App\Jobs\ResearchTrackAirplay;
use App\Models\Airplay;
use App\Models\AirplayFinding;
use App\Models\AirplaySearch;
use App\Models\Artist;
use App\Models\Label;
use App\Models\Release;
use App\Models\Track;
use App\Services\Airplay\AirplayResearch;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

const RESEARCH_SERP_URL = 'https://api.dataforseo.com/v3/serp/google/organic/live/advanced';

beforeEach(function () {
    config(['services.dataforseo.login' => 'example-login', 'services.dataforseo.password' => 'example-password', 'services.dataforseo.markets' => ['DE']]);
});

/**
 * Answer every search with the given organic results.
 *
 * @param  list<array{url: string, title?: string, description?: string, domain?: string}>  $results
 */
function fakeSearchResults(array $results): void
{
    Http::preventStrayRequests();
    Http::fake([RESEARCH_SERP_URL => Http::response(['status_code' => 20000, 'tasks' => [['status_code' => 20000, 'result' => [[
        'items' => array_map(fn (array $result): array => ['type' => 'organic', 'title' => 'Example Artist – Example Track', ...$result], $results),
    ]]]]])]);
}

/**
 * A track by "Example Artist" called "Example Track".
 */
function researchTrack(Label $label, array $attributes = []): Track
{
    return Track::factory()->create([
        'label_id' => $label->id, 'name' => 'Example Track',
        'artist_id' => Artist::factory()->create(['label_id' => $label->id, 'name' => 'Example Artist']),
        ...$attributes,
    ]);
}

test('a research keeps pages that name the track and may show a play, and leaves out shops and streaming services', function () {
    $label = Label::factory()->create();
    $track = researchTrack($label);
    fakeSearchResults([
        ['url' => 'https://radio.example.com/playlist', 'title' => 'Playlist of the morning show', 'description' => 'Example Artist with Example Track', 'domain' => 'radio.example.com'],
        ['url' => 'https://blog.example.org/review', 'title' => 'A review of Example Track', 'domain' => 'blog.example.org'],
        ['url' => 'https://radio.example.com/other', 'title' => 'A playlist with other music', 'domain' => 'radio.example.com'],
        ['url' => 'https://open.spotify.com/track/1', 'title' => 'Example Track', 'domain' => 'open.spotify.com'],
    ]);

    $found = app(AirplayResearch::class)->research($track);

    expect($found)->toBe(2)
        ->and(AirplayFinding::query()->orderBy('id')->get()->map->only(['label_id', 'track_id', 'status', 'is_radio', 'url', 'query'])->all())->toBe([
            ['label_id' => $label->id, 'track_id' => $track->id, 'status' => 'new', 'is_radio' => true, 'url' => 'https://radio.example.com/playlist', 'query' => '"Example Artist" "Example Track" radio playlist'],
            ['label_id' => $label->id, 'track_id' => $track->id, 'status' => 'new', 'is_radio' => false, 'url' => 'https://blog.example.org/review', 'query' => '"Example Artist" "Example Track" radio playlist'],
        ])
        ->and($track->refresh()->researched_at)->not->toBeNull();
});

test('a research also searches for each station a statement named and ties the findings to that play', function () {
    $label = Label::factory()->create();
    $track = researchTrack($label);
    $airplay = Airplay::factory()->create(['label_id' => $label->id, 'track_id' => $track->id, 'station' => 'Example Radio']);
    Http::preventStrayRequests();
    Http::fake([RESEARCH_SERP_URL => fn (Request $request) => Http::response(['status_code' => 20000, 'tasks' => [['status_code' => 20000, 'result' => [[
        'items' => str_ends_with($request[0]['keyword'], 'Example Radio')
            ? [['type' => 'organic', 'title' => 'Programme with Example Track', 'url' => 'https://example.com/programme', 'domain' => 'example.com']]
            : [],
    ]]]]])]);

    app(AirplayResearch::class)->research($track);

    expect(AirplayFinding::query()->sole()->only(['airplay_id', 'station', 'is_radio', 'query']))->toBe([
        'airplay_id' => $airplay->id, 'station' => 'Example Radio', 'is_radio' => true, 'query' => '"Example Artist" "Example Track" Example Radio',
    ]);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request[0]['keyword'] === '"Example Artist" "Example Track" radio playlist' && $request[0]['depth'] === 30);
});

test('a research looks in every configured country, asks for the national broadcaster and follows unidentified plays abroad', function () {
    config(['services.dataforseo.markets' => ['DE', 'GB']]);
    $label = Label::factory()->create();
    $track = researchTrack($label);
    Airplay::factory()->create(['label_id' => $label->id, 'track_id' => $track->id, 'station' => null, 'region' => 'FI']);
    fakeSearchResults([]);

    app(AirplayResearch::class)->research($track);

    $sent = Http::recorded()->map(fn (array $pair): array => [$pair[0][0]['keyword'], $pair[0][0]['location_name'], $pair[0][0]['language_code']])->all();

    expect($sent)->toBe([
        ['"Example Artist" "Example Track" radio playlist', 'Germany', 'de'],
        ['"Example Artist" "Example Track" radio playlist', 'United Kingdom', 'en'],
        ['"Example Artist" "Example Track" BBC', 'United Kingdom', 'en'],
        ['"Example Artist" "Example Track" radio playlist', 'Finland', 'fi'],
    ]);
});

test('every search is logged with what it returned, and a failed one does not lose the others', function () {
    config(['services.dataforseo.markets' => ['DE', 'GB']]);
    $label = Label::factory()->create();
    $track = researchTrack($label);
    $failing = ['status_code' => 20000, 'tasks' => [['status_code' => 40101, 'status_message' => 'Internal SE Server Error.']]];
    Http::preventStrayRequests();
    Http::fake([RESEARCH_SERP_URL => fn (Request $request) => Http::response($request[0]['location_name'] === 'Germany' ? $failing : ['status_code' => 20000, 'tasks' => [['status_code' => 20000, 'result' => [[
        'items' => [['type' => 'organic', 'title' => 'Playlist with Example Track', 'url' => 'https://radio.example.com/playlist', 'domain' => 'radio.example.com']],
    ]]]]])]);

    app(AirplayResearch::class)->research($track);

    expect(AirplaySearch::query()->withoutGlobalScope('label')->orderBy('id')->get()->map->only(['track_id', 'region', 'succeeded', 'new_findings', 'error'])->all())->toBe([
        ['track_id' => $track->id, 'region' => 'DE', 'succeeded' => false, 'new_findings' => 0, 'error' => 'The search failed: Internal SE Server Error.'],
        ['track_id' => $track->id, 'region' => 'GB', 'succeeded' => true, 'new_findings' => 1, 'error' => null],
        ['track_id' => $track->id, 'region' => 'GB', 'succeeded' => true, 'new_findings' => 0, 'error' => null],
    ])
        ->and(AirplaySearch::query()->withoutGlobalScope('label')->where('succeeded', true)->first()->results[0]['url'])->toBe('https://radio.example.com/playlist')
        ->and($track->refresh()->researched_at)->not->toBeNull();
});

test('a closer look at one play searches for it at its station and ties the findings to it', function () {
    $label = Label::factory()->create();
    $airplay = Airplay::factory()->create(['label_id' => $label->id, 'track_name' => 'Example Track', 'artist_name' => 'Example Artist', 'station' => 'Example Radio', 'region' => 'GB']);
    fakeSearchResults([['url' => 'https://example.com/show', 'title' => 'The evening show played Example Track', 'domain' => 'example.com']]);

    $found = app(AirplayResearch::class)->researchAirplay($airplay);

    expect($found)->toBe(1)
        ->and(AirplayFinding::query()->withoutGlobalScope('label')->sole()->only(['label_id', 'track_id', 'airplay_id', 'station']))->toBe([
            'label_id' => $label->id, 'track_id' => null, 'airplay_id' => $airplay->id, 'station' => 'Example Radio',
        ])
        ->and(Http::recorded()->map(fn (array $pair): string => $pair[0][0]['keyword'].' @'.$pair[0][0]['location_name'])->all())->toBe([
            '"Example Artist" "Example Track" Example Radio @United Kingdom',
            '"Example Artist" Example Radio playlist @United Kingdom',
            '"Example Track" Example Radio playlist @United Kingdom',
        ])
        ->and(AirplaySearch::query()->withoutGlobalScope('label')->where('airplay_id', $airplay->id)->count())->toBe(3);
});

test('a closer look at one play is queued from its row', function () {
    Queue::fake();
    $label = Label::factory()->create();
    $airplay = Airplay::factory()->create(['label_id' => $label->id]);
    $foreign = Airplay::factory()->create();
    $admin = labelAdmin($label);

    $this->actingAs($admin)->post(route('airplays.research.airplay', $airplay->id))->assertRedirect();
    $this->actingAs($admin)->post(route('airplays.research.airplay', $foreign->id))->assertNotFound();

    Queue::assertPushed(ResearchAirplayEntry::class, 1);
    Queue::assertPushed(ResearchAirplayEntry::class, fn (ResearchAirplayEntry $job): bool => $job->airplayId === $airplay->id);
});

test('a queued research shows as waiting, reports each search while it runs and clears when done', function () {
    Queue::fake();
    $label = Label::factory()->create();
    $airplay = Airplay::factory()->create(['label_id' => $label->id, 'track_name' => 'Example Track', 'artist_name' => 'Example Artist', 'station' => 'Example Radio', 'region' => 'GB']);
    $this->actingAs(labelAdmin($label))->post(route('airplays.research.airplay', $airplay->id));

    expect($airplay->refresh()->research_state)->toBe('queued');

    $steps = [];
    Http::preventStrayRequests();
    Http::fake([RESEARCH_SERP_URL => function () use (&$steps, $airplay) {
        $steps[] = $airplay->refresh()->only(['research_state', 'research_step']);

        return Http::response(['status_code' => 20000, 'tasks' => [['status_code' => 20000, 'result' => [['items' => []]]]]]);
    }]);

    (new ResearchAirplayEntry($airplay->id))->handle(app(AirplayResearch::class));

    expect($steps[0])->toBe(['research_state' => 'running', 'research_step' => 'Search 1 of 3 (GB): "Example Artist" "Example Track" Example Radio'])
        ->and($steps[2]['research_step'])->toStartWith('Search 3 of 3')
        ->and($airplay->refresh()->only(['research_state', 'research_step']))->toBe(['research_state' => null, 'research_step' => null]);
});

test('a research that fails says so on the track, and one that died without a word is not shown as running', function () {
    $label = Label::factory()->create();
    $failed = researchTrack($label);
    $died = Track::factory()->create(['label_id' => $label->id, 'research_state' => 'running', 'research_state_at' => now()->subMinutes(20)]);
    $waiting = Track::factory()->create(['label_id' => $label->id, 'name' => 'Sample Track', 'research_state' => 'queued', 'research_state_at' => now()->subHours(3)]);

    (new ResearchTrackAirplay($failed->id))->failed(new RuntimeException('The search failed: Internal SE Server Error.'));

    $this->actingAs(labelAdmin($label))
        ->get(route('airplays.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('trackResearch', 2)
            ->where('trackResearch.0', ['id' => $waiting->id, 'name' => 'Sample Track', 'state' => 'queued', 'step' => null])
            ->where('trackResearch.1', ['id' => $failed->id, 'name' => 'Example Track', 'state' => 'failed', 'step' => 'The search failed: Internal SE Server Error.']));

    expect($died->refresh()->research_state)->toBe('running');
});

test('a page that was found before is not listed again, even after it was dismissed', function () {
    $label = Label::factory()->create();
    $track = researchTrack($label);
    fakeSearchResults([['url' => 'https://radio.example.com/playlist', 'domain' => 'radio.example.com']]);

    app(AirplayResearch::class)->research($track);
    AirplayFinding::query()->sole()->update(['status' => AirplayFinding::DISMISSED]);
    $found = app(AirplayResearch::class)->research($track);

    expect($found)->toBe(0)
        ->and(AirplayFinding::query()->sole()->status)->toBe('dismissed');
});

test('a track stays due when the search fails', function () {
    $track = researchTrack(Label::factory()->create());
    Http::preventStrayRequests();
    Http::fake([RESEARCH_SERP_URL => Http::response(['status_code' => 40100, 'status_message' => 'You are not authorized.'], 401)]);

    expect(fn () => (new ResearchTrackAirplay($track->id))->handle(app(AirplayResearch::class)))->toThrow(RuntimeException::class)
        ->and($track->refresh()->researched_at)->toBeNull();
});

test('a research is queued for the chosen group of tracks', function (string $scope, array $expected) {
    Queue::fake();
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id]);
    $tracks = [
        'never' => Track::factory()->create(['label_id' => $label->id, 'release_id' => $release->id]),
        'old' => Track::factory()->create(['label_id' => $label->id, 'researched_at' => now()->subDays(32)]),
        'recent' => Track::factory()->create(['label_id' => $label->id, 'researched_at' => now()->subDays(30)]),
    ];
    $foreign = Track::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->post(route('airplays.research.store'), ['scope' => $scope, 'release_id' => $release->id, 'track_ids' => [$tracks['recent']->id, $foreign->id]])
        ->assertSessionHasNoErrors();

    Queue::assertPushed(ResearchTrackAirplay::class, count($expected));
    expect(Track::query()->withoutGlobalScope('label')->where('research_state', 'queued')->count())->toBe(count($expected));

    foreach ($expected as $key) {
        Queue::assertPushed(ResearchTrackAirplay::class, fn (ResearchTrackAirplay $job): bool => $job->trackId === $tracks[$key]->id);
    }
})->with([
    'not researched in the last 31 days' => ['stale', ['never', 'old']],
    'one release' => ['release', ['never']],
    'selected tracks' => ['tracks', ['recent']],
    'every track' => ['all', ['never', 'old', 'recent']],
]);

test('a research is not queued', function (Closure $arrange, array $input, string $message) {
    Queue::fake();
    $label = Label::factory()->create();
    Track::factory()->create(['label_id' => $label->id, 'researched_at' => now()]);
    $input = $arrange($input);

    $this->actingAs(labelAdmin($label))
        ->post(route('airplays.research.store'), $input)
        ->assertSessionHasErrors(['scope' => $message]);

    Queue::assertNothingPushed();
})->with([
    'when no track is due' => [fn (array $input) => $input, ['scope' => 'stale'], 'There are no tracks to research in this selection.'],
    'when web search is not set up' => [
        function (array $input) {
            config(['services.dataforseo.password' => null]);

            return $input;
        },
        ['scope' => 'all'],
        'Web search is not set up: DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD are missing.',
    ],
]);

test('the release of another label cannot be researched', function () {
    Queue::fake();
    $release = Release::factory()->create();
    Track::factory()->create(['label_id' => $release->label_id, 'release_id' => $release->id]);

    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->post(route('airplays.research.store'), ['scope' => 'release', 'release_id' => $release->id])
        ->assertNotFound();

    Queue::assertNothingPushed();
});

test('the airplay page lists new findings and how many tracks are due', function () {
    $label = Label::factory()->create();
    $track = Track::factory()->create(['label_id' => $label->id, 'name' => 'Example Track']);
    $finding = AirplayFinding::factory()->create(['label_id' => $label->id, 'track_id' => $track->id]);
    AirplayFinding::factory()->create(['label_id' => $label->id, 'track_id' => $track->id, 'status' => AirplayFinding::DISMISSED]);
    AirplayFinding::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->get(route('airplays.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('findings', 1)
            ->where('findings.0.id', $finding->id)
            ->where('findings.0.track', 'Example Track')
            ->has('searches', 0)
            ->where('staleCount', 1)
            ->where('staleDays', 31));
});

test('keeping a finding of a known play adds it to that play as a link found by search', function () {
    $label = Label::factory()->create();
    $track = Track::factory()->create(['label_id' => $label->id]);
    $airplay = Airplay::factory()->create(['label_id' => $label->id, 'track_id' => $track->id, 'origin' => AirplayOrigin::Statement]);
    $finding = AirplayFinding::factory()->create([
        'label_id' => $label->id, 'track_id' => $track->id, 'airplay_id' => $airplay->id, 'url' => 'https://example.com/programme', 'title' => 'Programme',
    ]);

    $this->actingAs(labelAdmin($label))->post(route('airplay-findings.keep', $finding->id))->assertRedirect();

    expect($airplay->refresh()->links)->toBe([['url' => 'https://example.com/programme', 'title' => 'Programme', 'description' => null, 'source' => 'search']])
        ->and($airplay->origin)->toBe(AirplayOrigin::Statement)
        ->and($finding->refresh()->status)->toBe('kept')
        ->and(Airplay::query()->withoutGlobalScope('label')->count())->toBe(1);
});

test('keeping a finding of an unknown play creates an airplay that came from the search', function () {
    $label = Label::factory()->create();
    $track = researchTrack($label, ['isrc' => 'ZZTST2600001']);
    $finding = AirplayFinding::factory()->create([
        'label_id' => $label->id, 'track_id' => $track->id, 'url' => 'https://radio.example.com/playlist', 'title' => 'Playlist', 'domain' => 'radio.example.com',
    ]);

    $this->actingAs(labelAdmin($label))->post(route('airplay-findings.keep', $finding->id))->assertRedirect();

    $airplay = Airplay::query()->withoutGlobalScope('label')->sole();

    expect($airplay->only(['label_id', 'track_id', 'track_name', 'artist_name', 'isrc', 'station']))->toBe([
        'label_id' => $label->id, 'track_id' => $track->id, 'track_name' => 'Example Track', 'artist_name' => 'Example Artist',
        'isrc' => 'ZZTST2600001', 'station' => 'radio.example.com',
    ])
        ->and($airplay->origin)->toBe(AirplayOrigin::Search)
        ->and($airplay->links[0]['url'])->toBe('https://radio.example.com/playlist')
        ->and($finding->refresh()->only(['status', 'airplay_id']))->toBe(['status' => 'kept', 'airplay_id' => $airplay->id]);
});

test('a finding is dismissed, and the finding of another label cannot be reached', function () {
    $label = Label::factory()->create();
    $finding = AirplayFinding::factory()->create(['label_id' => $label->id, 'track_id' => Track::factory()->create(['label_id' => $label->id])]);
    $foreign = AirplayFinding::factory()->create();
    $admin = labelAdmin($label);

    $this->actingAs($admin)->post(route('airplay-findings.dismiss', $finding->id))->assertRedirect();
    $this->actingAs($admin)->post(route('airplay-findings.dismiss', $foreign->id))->assertNotFound();
    $this->actingAs($admin)->post(route('airplay-findings.keep', $foreign->id))->assertNotFound();

    expect($finding->refresh()->status)->toBe('dismissed')
        ->and($foreign->refresh()->status)->toBe('new');
});

test('the command queues the tracks that are due longest, up to the limit per label', function () {
    Queue::fake();
    $label = Label::factory()->create();
    $never = Track::factory()->create(['label_id' => $label->id]);
    $oldest = Track::factory()->create(['label_id' => $label->id, 'researched_at' => now()->subDays(90)]);
    Track::factory()->create(['label_id' => $label->id, 'researched_at' => now()->subDays(40)]);
    Track::factory()->create(['label_id' => $label->id, 'researched_at' => now()->subDays(5)]);

    $this->artisan('royalties:research-airplay', ['--limit' => 2])->assertSuccessful();

    Queue::assertPushed(ResearchTrackAirplay::class, 2);
    Queue::assertPushed(ResearchTrackAirplay::class, fn (ResearchTrackAirplay $job): bool => $job->trackId === $never->id);
    Queue::assertPushed(ResearchTrackAirplay::class, fn (ResearchTrackAirplay $job): bool => $job->trackId === $oldest->id);
});

test('the command queues nothing when web search is not set up', function () {
    Queue::fake();
    config(['services.dataforseo.login' => null]);
    Track::factory()->create();

    $this->artisan('royalties:research-airplay')->assertSuccessful();

    Queue::assertNothingPushed();
});
