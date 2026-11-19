<?php

use App\Enums\LabelRole;
use App\Models\Contract;
use App\Models\ContractSplit;
use App\Models\Label;
use App\Models\Payee;
use App\Models\Territory;
use App\Models\User;
use App\Services\Airplay\WebSearch;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
                'dataforseo_login' => null, 'dataforseo_password_set' => false,
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

test('a DataForSEO login is proven against the service and stored encrypted without ever being sent back', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.dataforseo.com/v3/appendix/user_data' => Http::response(['status_code' => 20000])]);
    $label = Label::factory()->create(['name' => 'Example Records', 'currency' => 'EUR']);

    $this->actingAs(labelAdmin($label))
        ->put(route('label-settings.update'), ['name' => 'Example Records', 'currency' => 'EUR', 'dataforseo_login' => 'example-login', 'dataforseo_password' => 'example-password'])
        ->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('example-login:example-password')));
    expect($label->refresh()->dataforseo_password)->toBe('example-password')
        ->and(DB::table('labels')->where('id', $label->id)->value('dataforseo_password'))->not->toContain('example-password');

    $this->get(route('label-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings.dataforseo_login', 'example-login')
            ->where('settings.dataforseo_password_set', true)
            ->missing('settings.dataforseo_password'))
        ->assertDontSee('example-password');
});

test('a DataForSEO login the service refuses is not stored', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.dataforseo.com/v3/appendix/user_data' => Http::response(['status_code' => 40100], 401)]);
    $label = Label::factory()->create(['name' => 'Example Records', 'currency' => 'EUR']);

    $this->actingAs(labelAdmin($label))
        ->put(route('label-settings.update'), ['name' => 'Example Records', 'currency' => 'EUR', 'dataforseo_login' => 'example-login', 'dataforseo_password' => 'wrong'])
        ->assertSessionHasErrors(['dataforseo_login' => 'DataForSEO does not accept this login and password.']);

    expect($label->refresh()->dataforseo_login)->toBeNull();
});

test('an empty DataForSEO password keeps the stored one and an empty login removes both', function () {
    Http::preventStrayRequests();
    $label = Label::factory()->create(['name' => 'Example Records', 'currency' => 'EUR', 'dataforseo_login' => 'example-login', 'dataforseo_password' => 'example-password']);
    $this->actingAs(labelAdmin($label));

    $this->put(route('label-settings.update'), ['name' => 'Example Records', 'currency' => 'EUR', 'dataforseo_login' => 'example-login', 'dataforseo_password' => ''])
        ->assertSessionHasNoErrors();
    expect($label->refresh()->dataforseo_password)->toBe('example-password');

    $this->put(route('label-settings.update'), ['name' => 'Example Records', 'currency' => 'EUR', 'dataforseo_login' => '', 'dataforseo_password' => ''])
        ->assertSessionHasNoErrors();
    expect($label->refresh()->only(['dataforseo_login', 'dataforseo_password']))->toBe(['dataforseo_login' => null, 'dataforseo_password' => null]);
});

test('a research uses the login of its label', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.dataforseo.com/v3/serp/*' => Http::response(['status_code' => 20000, 'tasks' => [['status_code' => 20000, 'result' => [['items' => []]]]]])]);
    $label = Label::factory()->create(['dataforseo_login' => 'label-login', 'dataforseo_password' => 'label-password']);

    app(WebSearch::class)->for($label)->search('"Example Artist" "Example Track"', 'DE');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('label-login:label-password')));
});

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
