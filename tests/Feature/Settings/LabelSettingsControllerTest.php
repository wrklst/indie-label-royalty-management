<?php

use App\Enums\LabelRole;
use App\Models\Contract;
use App\Models\ContractSplit;
use App\Models\Label;
use App\Models\Payee;
use App\Models\Release;
use App\Models\Territory;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the label settings page shows the stored settings and territories', function () {
    $label = Label::factory()->create([
        'name' => 'Example Records', 'currency' => 'EUR', 'contact_email' => 'office@example.com',
        'accounts_mechanicals' => true, 'accounts_ppd' => false, 'accounts_reserves' => true,
        'importer_settings' => ['bandcamp' => ['disable_fee' => true, 'exclude_physical' => false]],
    ]);
    $second = Territory::factory()->create(['label_id' => $label->id, 'name' => 'Nordics', 'countries' => ['SE', 'NO']]);
    $first = Territory::factory()->create(['label_id' => $label->id, 'name' => 'GSA', 'countries' => ['DE', 'AT', 'CH']]);
    Territory::factory()->create(['label_id' => Label::factory(), 'name' => 'Foreign']);

    $this->actingAs(labelAdmin($label))
        ->get(route('label-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/label')
            ->where('settings', [
                'name' => 'Example Records', 'currency' => 'EUR', 'contact_email' => 'office@example.com',
                'accounts_mechanicals' => true, 'accounts_ppd' => false, 'accounts_reserves' => true,
                'bandcamp_disable_fee' => true, 'bandcamp_exclude_physical' => false,
                'airplay_markets' => ['DE', 'GB', 'US'],
                'zebralution_number' => null, 'zebralution_origin_country' => null, 'zebralution_price_group' => 20,
                'zebralution_work_rights' => 'administered by collecting society',
            ])
            ->where('territories', [
                ['id' => $first->id, 'name' => 'GSA', 'countries' => ['DE', 'AT', 'CH']],
                ['id' => $second->id, 'name' => 'Nordics', 'countries' => ['SE', 'NO']],
            ]));
});

test('the bandcamp options are off for a label that never saved them', function () {
    $label = Label::factory()->create(['importer_settings' => null]);

    $this->actingAs(labelAdmin($label))
        ->get(route('label-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings.bandcamp_disable_fee', false)
            ->where('settings.bandcamp_exclude_physical', false));
});

test('the label settings can be saved', function () {
    $label = Label::factory()->create(['name' => 'Old name', 'currency' => 'EUR']);
    $other = Label::factory()->create(['name' => 'Other label', 'currency' => 'EUR']);

    $this->actingAs(labelAdmin($label))
        ->from(route('label-settings.edit'))
        ->put(route('label-settings.update'), [
            'name' => 'Example Records', 'currency' => 'usd', 'contact_email' => 'office@example.com',
            'accounts_mechanicals' => true, 'accounts_ppd' => true, 'accounts_reserves' => false,
            'bandcamp_disable_fee' => true, 'bandcamp_exclude_physical' => false,
        ])
        ->assertRedirect(route('label-settings.edit'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Label settings saved.');

    $label->refresh();

    expect($label->name)->toBe('Example Records')
        ->and($label->currency)->toBe('USD')
        ->and($label->contact_email)->toBe('office@example.com')
        ->and($label->accounts_mechanicals)->toBeTrue()
        ->and($label->accounts_ppd)->toBeTrue()
        ->and($label->accounts_reserves)->toBeFalse()
        ->and($label->importer_settings)->toBe(['bandcamp' => ['disable_fee' => true, 'exclude_physical' => false]]);
    $this->assertDatabaseHas('labels', ['id' => $other->id, 'name' => 'Other label', 'currency' => 'EUR']);
});

test('the bandcamp options can be switched off again', function () {
    $label = Label::factory()->create(['importer_settings' => ['bandcamp' => ['disable_fee' => true, 'exclude_physical' => true]]]);

    $this->actingAs(labelAdmin($label))
        ->put(route('label-settings.update'), ['name' => $label->name, 'currency' => 'EUR', 'bandcamp_disable_fee' => false, 'bandcamp_exclude_physical' => true])
        ->assertSessionHasNoErrors();

    expect($label->refresh()->importer_settings)->toBe(['bandcamp' => ['disable_fee' => false, 'exclude_physical' => true]]);
});

test('the Zebralution delivery settings are stored, and a form without them leaves them alone', function () {
    $label = Label::factory()->create(['name' => 'Example Records', 'currency' => 'EUR']);
    $this->actingAs(labelAdmin($label));

    $this->put(route('label-settings.update'), [
        'name' => 'Example Records', 'currency' => 'EUR', 'zebralution_number' => '1100a1', 'zebralution_origin_country' => 'de',
        'zebralution_price_group' => '30', 'zebralution_work_rights' => '',
    ])->assertSessionHasNoErrors();

    $stored = ['zebralution' => ['number' => '1100a1', 'origin_country' => 'DE', 'price_group' => 30, 'work_rights' => 'administered by collecting society']];

    expect($label->refresh()->delivery_settings)->toBe($stored);

    $this->put(route('label-settings.update'), ['name' => 'Example Records', 'currency' => 'EUR'])->assertSessionHasNoErrors();

    expect($label->refresh()->delivery_settings)->toBe($stored);

    $this->get(route('label-settings.edit'))->assertInertia(fn ($page) => $page
        ->where('settings.zebralution_number', '1100a1')
        ->where('settings.zebralution_price_group', 30)
        ->where('zebralutionWorkRights.1', 'licensed to distributor by label'));
});

test('the airplay research countries are stored split, upper-cased and deduplicated', function () {
    $label = Label::factory()->create(['name' => 'Example Records', 'currency' => 'EUR']);

    $this->actingAs(labelAdmin($label))
        ->put(route('label-settings.update'), ['name' => 'Example Records', 'currency' => 'EUR', 'airplay_markets' => 'de, gb us DE'])
        ->assertSessionHasNoErrors();

    expect($label->refresh()->airplay_markets)->toBe(['DE', 'GB', 'US']);
});

test('a form without the airplay research countries leaves them alone', function () {
    $label = Label::factory()->create(['name' => 'Example Records', 'currency' => 'EUR', 'airplay_markets' => ['FR']]);

    $this->actingAs(labelAdmin($label))
        ->put(route('label-settings.update'), ['name' => 'Example Records', 'currency' => 'EUR'])
        ->assertSessionHasNoErrors();

    expect($label->refresh()->airplay_markets)->toBe(['FR']);
});

test('airplay research countries that are no country codes are rejected', function (string $value, string $error) {
    $label = Label::factory()->create(['name' => 'Example Records', 'currency' => 'EUR', 'airplay_markets' => ['FR']]);

    $this->actingAs(labelAdmin($label))
        ->put(route('label-settings.update'), ['name' => 'Example Records', 'currency' => 'EUR', 'airplay_markets' => $value])
        ->assertSessionHasErrors($error);

    expect($label->refresh()->airplay_markets)->toBe(['FR']);
})->with([
    'a country name' => ['Germany', 'airplay_markets.0'],
    'nothing' => ['', 'airplay_markets'],
]);

test('invalid label settings are rejected', function (string $field, mixed $value) {
    $label = Label::factory()->create(['name' => 'Example Records', 'currency' => 'EUR']);

    $this->actingAs(labelAdmin($label))
        ->put(route('label-settings.update'), ['name' => 'Changed', 'currency' => 'USD', $field => $value])
        ->assertSessionHasErrors($field);

    $this->assertDatabaseHas('labels', ['id' => $label->id, 'name' => 'Example Records', 'currency' => 'EUR']);
})->with([
    'empty name' => ['name', ''],
    'name above 255 characters' => ['name', str_repeat('a', 256)],
    'empty currency' => ['currency', ''],
    'currency with four letters' => ['currency', 'EURO'],
    'currency with digits' => ['currency', 'E1R'],
    'contact address that is no email' => ['contact_email', 'office'],
    'flag that is no boolean' => ['accounts_ppd', 'maybe'],
    'bandcamp option that is no boolean' => ['bandcamp_disable_fee', 'maybe'],
    'Zebralution price group that does not exist' => ['zebralution_price_group', 15],
    'Zebralution rights in other words' => ['zebralution_work_rights', 'ours'],
    'country of origin that is no code' => ['zebralution_origin_country', 'Germany'],
]);

test('the slug of a label cannot be changed through the settings', function () {
    $label = Label::factory()->create(['slug' => 'example-records']);

    $this->actingAs(labelAdmin($label))
        ->put(route('label-settings.update'), ['name' => 'Example Records', 'currency' => 'EUR', 'slug' => 'hijacked'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('labels', ['id' => $label->id, 'slug' => 'example-records']);
});

test('a territory is stored with its country codes split, upper-cased and deduplicated', function () {
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->from(route('label-settings.edit'))
        ->post(route('territories.store'), ['name' => 'GSA', 'countries' => 'de, at ch;DE'])
        ->assertRedirect(route('label-settings.edit'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Territory created.');

    $territory = Territory::query()->withoutGlobalScopes()->sole();

    expect($territory->label_id)->toBe($label->id)
        ->and($territory->name)->toBe('GSA')
        ->and($territory->countries)->toBe(['DE', 'AT', 'CH']);
});

test('a territory with invalid input is rejected', function (array $input, string $error) {
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->post(route('territories.store'), ['name' => 'GSA', 'countries' => 'de, at', ...$input])
        ->assertSessionHasErrors($error);

    $this->assertDatabaseCount('territories', 0);
})->with([
    'code with three letters' => [['countries' => 'deu'], 'countries.0'],
    'code with a digit' => [['countries' => 'de, a1'], 'countries.1'],
    'no codes' => [['countries' => ''], 'countries'],
    'only separators' => [['countries' => ' , ; '], 'countries'],
    'no name' => [['name' => ''], 'name'],
]);

test('a territory can be deleted', function () {
    $label = Label::factory()->create();
    $territory = Territory::factory()->create(['label_id' => $label->id]);

    $this->actingAs(labelAdmin($label))
        ->from(route('label-settings.edit'))
        ->delete(route('territories.destroy', $territory->id))
        ->assertRedirect(route('label-settings.edit'))
        ->assertInertiaFlash('toast.message', 'Territory deleted.');

    $this->assertModelMissing($territory);
});

test('a territory that splits refer to is kept', function () {
    $label = Label::factory()->create();
    $territory = Territory::factory()->create(['label_id' => $label->id]);
    $split = ContractSplit::factory()->create([
        'contract_id' => Contract::factory()->create(['label_id' => $label->id]),
        'payee_id' => Payee::factory()->create(['label_id' => $label->id]),
        'territory_id' => $territory->id,
    ]);

    $this->actingAs(labelAdmin($label))
        ->from(route('label-settings.edit'))
        ->delete(route('territories.destroy', $territory->id))
        ->assertRedirect(route('label-settings.edit'))
        ->assertInertiaFlash('toast.message', 'This territory is used by contract splits. Change those splits first.');

    $this->assertModelExists($territory);
    expect($split->refresh()->territory_id)->toBe($territory->id);
});

test('a territory of another label cannot be deleted', function () {
    $label = Label::factory()->create();
    $foreign = Territory::factory()->create(['label_id' => Label::factory()]);

    $this->actingAs(labelAdmin($label))->delete(route('territories.destroy', $foreign->id))->assertNotFound();

    $this->assertModelExists($foreign);
});

test('a payee cannot reach the label settings', function () {
    $label = Label::factory()->create(['name' => 'Example Records']);
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $territory = Territory::factory()->create(['label_id' => $label->id]);
    $user = User::factory()->create(['current_label_id' => $label->id]);
    $label->users()->attach($user, ['role' => LabelRole::Payee->value, 'payee_id' => $payee->id]);

    $this->actingAs($user)->get(route('label-settings.edit'))->assertRedirect(route('portal.index'));
    $this->actingAs($user)->put(route('label-settings.update'), ['name' => 'Hijacked', 'currency' => 'USD'])->assertForbidden();
    $this->actingAs($user)->post(route('territories.store'), ['name' => 'Sneaky', 'countries' => 'de'])->assertForbidden();
    $this->actingAs($user)->delete(route('territories.destroy', $territory->id))->assertForbidden();

    $this->assertDatabaseHas('labels', ['id' => $label->id, 'name' => 'Example Records']);
    $this->assertDatabaseCount('territories', 1);
});

test('a distributor that is switched off no longer shows what is built for it', function () {
    $label = Label::factory()->create(['name' => 'Example Records', 'currency' => 'EUR']);
    $release = Release::factory()->create(['label_id' => $label->id]);
    $admin = labelAdmin($label);

    $this->actingAs($admin)->get(route('label-settings.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('distributors', [['key' => 'zebralution', 'name' => 'Zebralution', 'enabled' => true]])
        ->where('label.distributors', ['zebralution']));
    $this->get(route('play-counts.index'))->assertInertia(fn (Assert $page) => $page->where('zebralutionExport', fn (?string $url): bool => $url !== null));
    $this->get(route('data-imports.create'))->assertInertia(fn (Assert $page) => $page->where('sources', fn ($sources): bool => collect($sources)->contains('key', 'zebralution')));

    $this->put(route('label-settings.update'), ['name' => 'Example Records', 'currency' => 'EUR', 'distributors' => ['zebralution' => '0']])->assertSessionHasNoErrors();

    expect($label->refresh()->delivery_settings)->toBe(['distributors' => ['zebralution' => false]]);

    $this->get(route('label-settings.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('distributors.0.enabled', false)
        ->where('label.distributors', []));
    $this->get(route('play-counts.index'))->assertInertia(fn (Assert $page) => $page->where('zebralutionExport', null));
    $this->get(route('data-imports.create'))->assertInertia(fn (Assert $page) => $page
        ->where('sources', fn ($sources): bool => ! collect($sources)->contains('key', 'zebralution') && collect($sources)->contains('key', 'bandcamp')));
    $this->get(route('releases.edit', $release->id))->assertInertia(fn (Assert $page) => $page->where('genreOptions', []));
    $this->get(route('releases.zebralution-package', $release->id))->assertNotFound();

    // A form without the distributors leaves the choice alone.
    $this->put(route('label-settings.update'), ['name' => 'Example Records', 'currency' => 'EUR'])->assertSessionHasNoErrors();

    expect($label->refresh()->usesDistributor('zebralution'))->toBeFalse();
});

test('a distributor this installation does not know is refused', function () {
    $label = Label::factory()->create(['name' => 'Example Records', 'currency' => 'EUR']);

    $this->actingAs(labelAdmin($label))
        ->put(route('label-settings.update'), ['name' => 'Example Records', 'currency' => 'EUR', 'distributors' => ['example' => '1']])
        ->assertSessionHasErrors('distributors');
});
