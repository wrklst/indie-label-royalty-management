<?php

use App\Models\BandcampConnection;
use App\Models\Label;
use App\Models\Release;
use App\Support\Services;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A label that switched the service off.
 */
function labelWithout(string ...$services): Label
{
    return Label::factory()->create(['delivery_settings' => ['distributors' => array_fill_keys($services, false)]]);
}

test('a label that never chose works with every service, and the stored choice of Zebralution is kept', function () {
    $label = Label::factory()->create(['delivery_settings' => ['distributors' => ['zebralution' => false]]]);

    expect(Label::factory()->create()->services())->toBe(Services::keys())
        ->and($label->usesService('zebralution'))->toBeFalse()
        ->and($label->services())->toBe(['bandcamp', 'subvert', 'rightz-audio'])
        ->and($label->usesService('example'))->toBeFalse();
});

test('a service that is switched off no longer shows what is built for it', function () {
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id]);
    $this->actingAs(labelAdmin($label));

    $this->get(route('play-counts.index'))->assertInertia(fn (Assert $page) => $page->where('zebralutionExport', fn (?string $url): bool => $url !== null));
    $this->get(route('data-imports.create'))->assertInertia(fn (Assert $page) => $page->where('sources', fn ($sources): bool => collect($sources)->pluck('key')->contains('zebralution')));

    $this->put(route('connections.service', 'zebralution'), ['enabled' => '0'])->assertSessionHasNoErrors();

    expect($label->refresh()->delivery_settings)->toBe(['distributors' => ['zebralution' => false]]);

    $this->get(route('play-counts.index'))->assertInertia(fn (Assert $page) => $page->where('zebralutionExport', null));
    $this->get(route('data-imports.create'))->assertInertia(fn (Assert $page) => $page
        ->where('sources', fn ($sources): bool => ! collect($sources)->pluck('key')->contains('zebralution') && collect($sources)->pluck('key')->contains('bandcamp')));
    $this->get(route('releases.edit', $release->id))->assertInertia(fn (Assert $page) => $page->where('genreOptions', [])->where('label.services', ['bandcamp', 'subvert', 'rightz-audio']));
    $this->get(route('releases.zebralution-package', $release->id))->assertNotFound();
});

test('the statement layouts of a switched-off service are not offered, and the rest are', function (string $service, string $source) {
    $label = labelWithout($service);

    $this->actingAs(labelAdmin($label))->get(route('data-imports.create'))->assertInertia(fn (Assert $page) => $page
        ->where('sources', fn ($sources): bool => ! collect($sources)->pluck('key')->contains($source) && collect($sources)->pluck('key')->contains('template')));
})->with([
    'Bandcamp' => ['bandcamp', 'bandcamp'],
    'Rightz Audio' => ['rightz-audio', 'rightz-audio'],
]);

test('the delivery ZIPs of a switched-off service refuse and the others still answer', function (string $service, string $route) {
    $label = labelWithout($service);
    $release = Release::factory()->create(['label_id' => $label->id]);
    $this->actingAs(labelAdmin($label));

    $this->get(route($route, $release->id))->assertNotFound();

    // The generic package and the metadata sheet are never switched off.
    $this->get(route('releases.metadata'))->assertOk();
    expect(route('releases.package', $release->id))->not->toBeEmpty();
})->with([
    'Subvert' => ['subvert', 'releases.subvert-package'],
    'Bandcamp' => ['bandcamp', 'releases.bandcamp-package'],
    'Zebralution' => ['zebralution', 'releases.zebralution-package'],
]);

test('a delivery ZIP of a service the label works with is not refused as switched off', function (string $route) {
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id]);

    // The release has no cover and no tracks: the release page says what is missing instead of a 404.
    $this->actingAs(labelAdmin($label))->get(route($route, $release->id))->assertRedirect(route('releases.show', $release));
})->with(['releases.subvert-package', 'releases.bandcamp-package', 'releases.zebralution-package']);

test('the Bandcamp page and its sync refuse a label that switched Bandcamp off', function () {
    $this->actingAs(labelAdmin(labelWithout('bandcamp')));

    $this->get(route('bandcamp.edit'))->assertNotFound();
    $this->post(route('bandcamp.sync'))->assertNotFound();
    $this->post(route('bandcamp.connect'), ['client_id' => 'example', 'client_secret' => 'example'])->assertNotFound();
});

test('a stored Bandcamp login can still be removed after Bandcamp was switched off', function () {
    $label = labelWithout('bandcamp');
    BandcampConnection::factory()->create(['label_id' => $label->id]);

    $this->actingAs(labelAdmin($label))->delete(route('bandcamp.destroy'))->assertRedirect();

    expect(BandcampConnection::query()->withoutGlobalScopes()->where('label_id', $label->id)->exists())->toBeFalse();
});

test('the shared label says which services it works with', function () {
    $this->actingAs(labelAdmin(labelWithout('subvert', 'bandcamp')))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('label.services', ['zebralution', 'rightz-audio']));
});
