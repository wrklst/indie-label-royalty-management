<?php

use App\Models\Item;
use App\Models\Release;
use App\Models\Track;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    toolScenario();
});

test('the mechanicals rates page shows the release and its tracks', function () {
    $this->release->update(['accounts_mechanicals' => true, 'mechanicals_rate' => '0.1']);
    $track = toolTrack('Example Track', ['track_number' => 1, 'duration' => 200, 'mechanicals_rate' => '0.25', 'accounts_mechanicals' => false]);

    $this->get(route('royalty-tools.rates.edit', ['mechanicals', $this->release]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('royalty-tools/rates')
            ->where('tool', 'mechanicals')
            ->where('release.rate', '0.1')
            ->where('release.accounts_mechanicals', true)
            ->where('tracks', [['id' => $track->id, 'name' => 'Example Track', 'track_number' => 1, 'duration' => 200, 'accounts_mechanicals' => false, 'rate' => '0.25']])
            ->where('items', []));
});

test('the PPD rates page lists the items of the release except the ones the tools book on', function () {
    $this->release->update(['ppd_rate' => '2.5']);
    $item = Item::factory()->create(['label_id' => $this->label->id, 'release_id' => $this->release->id, 'name' => 'Example Vinyl', 'format_group' => 'Physical', 'ppd_rate' => '7']);
    Item::factory()->create(['label_id' => $this->label->id, 'release_id' => $this->release->id, 'name' => 'Example Release [PPD Income]', 'tool_role' => 'ppd-income']);

    $this->get(route('royalty-tools.rates.edit', ['ppd', $this->release]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tool', 'ppd')
            ->where('release.rate', '2.5')
            ->where('items', fn ($items) => $items->sortBy('id')->values()->all() === [
                ['id' => $this->release->items()->where('is_additionals', true)->sole()->id, 'name' => '[Additionals]', 'format_group' => null, 'track' => null, 'rate' => null],
                ['id' => $item->id, 'name' => 'Example Vinyl', 'format_group' => 'Physical', 'track' => null, 'rate' => '7'],
            ]));
});

test('mechanicals rates and switches are saved for the release and each track', function () {
    $on = toolTrack('Switched On');
    $off = toolTrack('Switched Off', ['mechanicals_rate' => '0.5']);
    $follows = toolTrack('Follows', ['accounts_mechanicals' => true, 'ppd_rate' => '9']);

    $this->from(route('royalty-tools.rates.edit', ['mechanicals', $this->release]))
        ->put(route('royalty-tools.rates.update', ['mechanicals', $this->release]), [
            'rate' => '0.091',
            'accounts_mechanicals' => true,
            'tracks' => [
                ['id' => $on->id, 'rate' => '0.12', 'accounts_mechanicals' => true],
                ['id' => $off->id, 'rate' => null, 'accounts_mechanicals' => false],
                ['id' => $follows->id, 'rate' => null, 'accounts_mechanicals' => null],
            ],
        ])
        ->assertRedirect(route('royalty-tools.rates.edit', ['mechanicals', $this->release]))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Rates saved.');

    expect($this->release->refresh())->accounts_mechanicals->toBeTrue()->mechanicals_rate->toEqual('0.091')
        ->and($on->refresh())->accounts_mechanicals->toBeTrue()->mechanicals_rate->toEqual('0.12')
        ->and($off->refresh())->accounts_mechanicals->toBeFalse()->mechanicals_rate->toBeNull()
        ->and($follows->refresh())->accounts_mechanicals->toBeNull()->ppd_rate->toEqual('9');
});

test('PPD rates are saved for the release, its tracks and its items without touching mechanicals', function () {
    $this->release->update(['accounts_mechanicals' => true, 'mechanicals_rate' => '0.1']);
    $track = toolTrack('Example Track', ['accounts_mechanicals' => false]);
    $item = Item::factory()->create(['label_id' => $this->label->id, 'release_id' => $this->release->id, 'ppd_rate' => '1']);
    $cleared = Item::factory()->create(['label_id' => $this->label->id, 'release_id' => $this->release->id, 'ppd_rate' => '1']);

    $this->put(route('royalty-tools.rates.update', ['ppd', $this->release]), [
        'rate' => '2',
        'tracks' => [['id' => $track->id, 'rate' => '3']],
        'items' => [['id' => $item->id, 'rate' => '5.25'], ['id' => $cleared->id, 'rate' => null]],
    ])->assertSessionHasNoErrors();

    expect($this->release->refresh())->ppd_rate->toEqual('2')->accounts_mechanicals->toBeTrue()->mechanicals_rate->toEqual('0.1')
        ->and($track->refresh())->ppd_rate->toEqual('3')->accounts_mechanicals->toBeFalse()
        ->and($item->refresh()->ppd_rate)->toEqual('5.25')
        ->and($cleared->refresh()->ppd_rate)->toBeNull();
});

test('rates are refused when they are invalid or point outside the release', function (Closure $input, string $tool, string $field) {
    $track = toolTrack('Example Track');

    $this->put(route('royalty-tools.rates.update', [$tool, $this->release]), ['accounts_mechanicals' => false, ...$input($track)])
        ->assertSessionHasErrors($field);

    expect($this->release->refresh()->mechanicals_rate)->toBeNull()
        ->and($this->release->ppd_rate)->toBeNull();
})->with([
    'a negative release rate' => [fn () => ['rate' => '-1'], 'ppd', 'rate'],
    'a rate that is not a number' => [fn () => ['rate' => 'lots'], 'mechanicals', 'rate'],
    'more than six decimals' => [fn () => ['rate' => '0.1234567'], 'mechanicals', 'rate'],
    'a negative track rate' => [fn (Track $track) => ['rate' => '1', 'tracks' => [['id' => $track->id, 'rate' => '-0.1']]], 'ppd', 'tracks.0.rate'],
    'a track of another release' => [fn () => ['rate' => '1', 'tracks' => [['id' => Track::factory()->create(['label_id' => test()->label->id])->id, 'rate' => '1']]], 'ppd', 'tracks.0.id'],
    'an item of another release' => [fn () => ['rate' => '1', 'items' => [['id' => Item::factory()->create(['label_id' => test()->label->id])->id, 'rate' => '1']]], 'ppd', 'items.0.id'],
    'an item a tool books on' => [fn () => ['rate' => '1', 'items' => [['id' => Item::factory()->create(['label_id' => test()->label->id, 'release_id' => test()->release->id, 'tool_role' => 'ppd-income'])->id, 'rate' => '1']]], 'ppd', 'items.0.id'],
]);

test('mechanicals rates need the release switch', function () {
    $this->put(route('royalty-tools.rates.update', ['mechanicals', $this->release]), ['rate' => '0.1'])
        ->assertSessionHasErrors('accounts_mechanicals');
});

test('rates of a release of another label cannot be seen or changed', function () {
    $foreign = Release::factory()->create();

    $this->get(route('royalty-tools.rates.edit', ['ppd', $foreign]))->assertNotFound();
    $this->put(route('royalty-tools.rates.update', ['ppd', $foreign]), ['rate' => '2'])->assertNotFound();

    expect($foreign->refresh()->ppd_rate)->toBeNull();
});

test('there is no rates page for reserves or for a tool that is switched off', function () {
    $this->get("/royalty-tools/reserves/rates/{$this->release->id}")->assertNotFound();

    $this->label->update(['accounts_ppd' => false]);

    $this->put(route('royalty-tools.rates.update', ['ppd', $this->release]), ['rate' => '2'])->assertNotFound();
    expect($this->release->refresh()->ppd_rate)->toBeNull();
});

test('a payee cannot change rates', function () {
    $this->actingAs(toolPayeeUser($this->label));

    $this->put(route('royalty-tools.rates.update', ['ppd', $this->release]), ['rate' => '2'])->assertForbidden();
});

test('the track and item forms accept the switch and the rate the tools read', function () {
    $track = toolTrack('Example Track');
    $item = Item::factory()->create(['label_id' => $this->label->id, 'release_id' => $this->release->id]);

    $this->put(route('tracks.update', $track), ['name' => 'Example Track', 'accounts_mechanicals' => true])->assertSessionHasNoErrors();
    $this->put(route('items.update', $item), ['release_id' => $this->release->id, 'ppd_rate' => '4.5'])->assertSessionHasNoErrors();
    $this->put(route('items.update', $item), ['release_id' => $this->release->id, 'ppd_rate' => '-1'])->assertSessionHasErrors('ppd_rate');

    expect($track->refresh()->accounts_mechanicals)->toBeTrue()
        ->and($item->refresh()->ppd_rate)->toEqual('4.5');
});
