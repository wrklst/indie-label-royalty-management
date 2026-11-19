<?php

use App\Models\Airplay;
use App\Models\AirplaySearch;
use App\Models\Label;
use App\Models\Track;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const SERP_URL = 'https://api.dataforseo.com/v3/serp/google/organic/live/advanced';

beforeEach(function () {
    config(['services.dataforseo.login' => 'example-login', 'services.dataforseo.password' => 'example-password']);
});

test('the airplay list shows the plays of the current label only', function () {
    $label = Label::factory()->create();
    $airplay = Airplay::factory()->create([
        'label_id' => $label->id, 'track_name' => 'Example Track', 'artist_name' => 'Example Artist', 'station' => 'Example Radio',
        'period_start' => '2025-01-01', 'period_end' => '2025-12-31',
        'links' => [['url' => 'https://example.com/playlist', 'title' => 'Playlist', 'description' => null]],
    ]);
    Airplay::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->get(route('airplays.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('airplays/index')
            ->has('airplays', 1)
            ->where('airplays.0.id', $airplay->id)
            ->where('airplays.0.origin', 'manual')
            ->where('airplays.0.period_end', '2025-12-31')
            ->where('airplays.0.links.0.url', 'https://example.com/playlist')
            ->where('airplays.0.search_query', '"Example Artist" "Example Track" Example Radio')
            ->where('searchAvailable', true));
});

test('an airplay of a catalog track takes its names from the track', function () {
    $label = Label::factory()->create();
    $track = Track::factory()->create(['label_id' => $label->id, 'name' => 'Example Track', 'isrc' => 'ZZTST2600001']);

    $this->actingAs(labelAdmin($label))
        ->from(route('airplays.index'))
        ->post(route('airplays.store'), ['track_id' => $track->id, 'station' => 'Example Radio', 'region' => 'de', 'period_start' => '2026-03-31', 'period_end' => ''])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('airplays.index'));

    $this->assertDatabaseHas('airplays', [
        'label_id' => $label->id, 'origin' => 'manual', 'track_id' => $track->id, 'track_name' => 'Example Track', 'isrc' => 'ZZTST2600001',
        'station' => 'Example Radio', 'region' => 'DE', 'period_end' => null,
    ]);
});

test('an airplay can be entered without knowing the station', function () {
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->post(route('airplays.store'), ['track_name' => 'Example Track', 'station' => '', 'region' => 'GB'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('airplays', ['label_id' => $label->id, 'track_name' => 'Example Track', 'station' => null, 'region' => 'GB']);
});

test('an airplay is refused', function (array $input, string $field) {
    $label = Label::factory()->create();
    $foreignTrack = Track::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->post(route('airplays.store'), ['track_name' => 'Example Track', 'station' => 'Example Radio', ...$input, ...($field === 'track_id' ? ['track_id' => $foreignTrack->id] : [])])
        ->assertSessionHasErrors($field);

    $this->assertDatabaseCount('airplays', 0);
})->with([
    'without a track' => [['track_name' => ''], 'track_name'],
    'for a track of another label' => [[], 'track_id'],
    'when it ends before it starts' => [['period_start' => '2026-03-31', 'period_end' => '2026-03-01'], 'period_end'],
    'with a country that is not a code' => [['region' => 'Germany'], 'region'],
]);

test('an airplay is updated and deleted', function () {
    $label = Label::factory()->create();
    $airplay = Airplay::factory()->create(['label_id' => $label->id]);
    $admin = labelAdmin($label);

    $this->actingAs($admin)
        ->put(route('airplays.update', $airplay->id), ['track_name' => 'Example Track', 'station' => 'Sample FM', 'notes' => 'Played in the morning show.'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('airplays', ['id' => $airplay->id, 'station' => 'Sample FM', 'notes' => 'Played in the morning show.']);

    $this->actingAs($admin)->delete(route('airplays.destroy', $airplay->id))->assertRedirect();

    $this->assertModelMissing($airplay);
});

test('the airplay of another label cannot be reached', function () {
    $airplay = Airplay::factory()->create();
    $admin = labelAdmin(Label::factory()->create());

    $this->actingAs($admin)->put(route('airplays.update', $airplay->id), ['track_name' => 'Example Track', 'station' => 'Sample FM'])->assertNotFound();
    $this->actingAs($admin)->post(route('airplays.links.store', $airplay->id), ['url' => 'https://example.com/playlist'])->assertNotFound();
    $this->actingAs($admin)->postJson(route('airplays.search', $airplay->id))->assertNotFound();
    $this->actingAs($admin)->delete(route('airplays.destroy', $airplay->id))->assertNotFound();
});

test('a link is saved once and can be removed again', function () {
    $label = Label::factory()->create();
    $airplay = Airplay::factory()->create(['label_id' => $label->id]);
    $admin = labelAdmin($label);

    $this->actingAs($admin)->post(route('airplays.links.store', $airplay->id), ['url' => 'https://example.com/playlist', 'title' => 'First title'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('airplays.links.store', $airplay->id), ['url' => 'https://example.com/playlist', 'title' => 'Playlist', 'source' => 'search'])->assertSessionHasNoErrors();

    expect($airplay->refresh()->links)->toBe([['url' => 'https://example.com/playlist', 'title' => 'Playlist', 'description' => null, 'source' => 'search']]);

    $this->actingAs($admin)->delete(route('airplays.links.destroy', $airplay->id), ['url' => 'https://example.com/playlist'])->assertSessionHasNoErrors();

    expect($airplay->refresh()->links)->toBeNull();
});

test('a link that is not a web address is refused', function () {
    $label = Label::factory()->create();
    $airplay = Airplay::factory()->create(['label_id' => $label->id]);

    $this->actingAs(labelAdmin($label))
        ->post(route('airplays.links.store', $airplay->id), ['url' => 'javascript:alert(1)'])
        ->assertSessionHasErrors('url');

    expect($airplay->refresh()->links)->toBeNull();
});

test('a web search returns the organic results for the play', function () {
    $label = Label::factory()->create();
    $airplay = Airplay::factory()->create(['label_id' => $label->id, 'track_name' => 'Example Track', 'artist_name' => 'Example Artist', 'station' => 'Example Radio']);
    Http::preventStrayRequests();
    Http::fake([SERP_URL => Http::response(['status_code' => 20000, 'tasks' => [['status_code' => 20000, 'result' => [['items' => [
        ['type' => 'paid', 'title' => 'Advert', 'url' => 'https://example.com/advert'],
        ['type' => 'organic', 'title' => 'Playlist', 'url' => 'https://example.com/playlist', 'description' => 'Example Track was played.', 'domain' => 'example.com'],
    ]]]]]])]);

    $this->actingAs(labelAdmin($label))
        ->postJson(route('airplays.search', $airplay->id))
        ->assertOk()
        ->assertExactJson(['results' => [
            ['title' => 'Playlist', 'url' => 'https://example.com/playlist', 'description' => 'Example Track was played.', 'domain' => 'example.com'],
        ]]);

    expect(AirplaySearch::query()->withoutGlobalScope('label')->sole()->only(['airplay_id', 'query', 'succeeded', 'results']))->toBe([
        'airplay_id' => $airplay->id, 'query' => '"Example Artist" "Example Track" Example Radio', 'succeeded' => true,
        'results' => [['title' => 'Playlist', 'url' => 'https://example.com/playlist', 'description' => 'Example Track was played.', 'domain' => 'example.com']],
    ]);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('example-login:example-password'))
        && $request[0]['resolve_goto_urls'] === true
        && $request[0]['keyword'] === '"Example Artist" "Example Track" Example Radio');
});

test('a web search uses the words the user typed', function () {
    $label = Label::factory()->create();
    $airplay = Airplay::factory()->create(['label_id' => $label->id]);
    Http::preventStrayRequests();
    Http::fake([SERP_URL => Http::response(['status_code' => 20000, 'tasks' => [['status_code' => 20000, 'result' => [['items' => null]]]]])]);

    $this->actingAs(labelAdmin($label))
        ->postJson(route('airplays.search', $airplay->id), ['query' => 'Example Radio playlist'])
        ->assertOk()
        ->assertExactJson(['results' => []]);

    Http::assertSent(fn (Request $request): bool => $request[0]['keyword'] === 'Example Radio playlist');
});

test('a web search that fails inside the search service is tried again and partial results are used', function () {
    $label = Label::factory()->create();
    $airplay = Airplay::factory()->create(['label_id' => $label->id]);
    Http::preventStrayRequests();
    Http::fake([SERP_URL => Http::sequence()
        ->push(['status_code' => 20000, 'tasks' => [['status_code' => 40101, 'status_message' => 'Internal SE Server Error.']]])
        ->push(['status_code' => 20000, 'tasks' => [['status_code' => 40101, 'status_message' => 'Internal SE Server Error.']]])
        ->push(['status_code' => 20000, 'tasks' => [['status_code' => 20100, 'status_message' => 'Task completed with partial results.', 'result' => [['items' => [
            ['type' => 'organic', 'title' => 'Playlist', 'url' => 'https://example.com/playlist'],
        ]]]]]])]);

    $this->actingAs(labelAdmin($label))
        ->postJson(route('airplays.search', $airplay->id))
        ->assertOk()
        ->assertJsonPath('results.0.url', 'https://example.com/playlist');
});

test('a failed web search reports why', function (Closure $arrange, string $message) {
    $label = Label::factory()->create();
    $airplay = Airplay::factory()->create(['label_id' => $label->id]);
    Http::preventStrayRequests();
    $arrange();

    $this->actingAs(labelAdmin($label))
        ->postJson(route('airplays.search', $airplay->id))
        ->assertJsonValidationErrors(['query' => $message]);

    expect(AirplaySearch::query()->withoutGlobalScope('label')->sole()->only(['succeeded', 'error']))->toBe(['succeeded' => false, 'error' => $message]);
})->with([
    'when the search service keeps failing' => [
        fn () => Http::fake([SERP_URL => Http::response(['status_code' => 20000, 'tasks' => [['status_code' => 40101, 'status_message' => 'Internal SE Server Error.']]])]),
        'The search failed: Internal SE Server Error.',
    ],
    'when the service refuses the task' => [
        fn () => Http::fake([SERP_URL => Http::response(['status_code' => 20000, 'tasks' => [['status_code' => 40200, 'status_message' => 'Payment Required.']]])]),
        'The search failed: Payment Required.',
    ],
    'when the login is wrong' => [
        fn () => Http::fake([SERP_URL => Http::response(['status_code' => 40100, 'status_message' => 'You are not authorized.'], 401)]),
        'The search failed: You are not authorized.',
    ],
    'when no login is set' => [
        fn () => config(['services.dataforseo.login' => null]),
        'Web search is not set up. Enter the DataForSEO login under Settings → Label.',
    ],
]);
