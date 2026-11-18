<?php

use App\Enums\LabelRole;
use App\Models\BandcampConnection;
use App\Models\Label;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => Http::preventStrayRequests());

const BANDCAMP_BANDS = [[
    'band_id' => 1000001, 'name' => 'Example Records', 'subdomain' => 'example-records',
    'member_bands' => [['band_id' => 1000002, 'name' => 'Example Artist', 'subdomain' => 'example-artist']],
]];

/**
 * Bandcamp accepting the credentials and listing one label account.
 */
function fakeBandcampConnect(): void
{
    Http::fake([
        BANDCAMP_TOKEN_URL => Http::response(['ok' => true, 'access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => 3600]),
        BANDCAMP_BANDS_URL => Http::response(['bands' => BANDCAMP_BANDS]),
    ]);
}

test('the page shows the state of the connection without any secret', function () {
    config(['inertia.ssr.enabled' => false]);
    $label = Label::factory()->create();
    BandcampConnection::factory()->create([
        'label_id' => $label->id, 'bands' => BANDCAMP_BANDS, 'member_band_id' => 1000002,
        'last_synced_until' => '2026-02-10 12:00:00', 'last_sync_at' => '2026-02-10 12:00:05', 'last_sync_status' => 'ok', 'last_sync_message' => '2 transactions imported.',
    ]);

    $response = $this->actingAs(labelAdmin($label))->get(route('bandcamp.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/bandcamp')
        ->where('connection', [
            'bands' => BANDCAMP_BANDS,
            'band_id' => 1000001,
            'member_band_id' => 1000002,
            'enabled' => true,
            'sync_from' => '2026-01-01',
            'last_synced_until' => '2026-02-10T12:00:00+00:00',
            'last_sync_at' => '2026-02-10T12:00:05+00:00',
            'last_sync_status' => 'ok',
            'last_sync_message' => '2 transactions imported.',
        ]));

    expect($response->getContent())->not->toContain('4711')
        ->not->toContain('example-client-secret')
        ->not->toContain('example-refresh-token')
        ->not->toContain('example-access-token');
});

test('the page shows no connection for a label that has none, even when another label is connected', function () {
    config(['inertia.ssr.enabled' => false]);
    BandcampConnection::factory()->create();

    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->get(route('bandcamp.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('connection', null));
});

test('guests are sent to the login', function () {
    $this->get(route('bandcamp.edit'))->assertRedirect(route('login'));
    $this->post(route('bandcamp.sync'))->assertRedirect(route('login'));
});

test('a payee user can neither see nor change the connection', function (string $method, string $route) {
    $label = Label::factory()->create();
    BandcampConnection::factory()->create(['label_id' => $label->id]);
    $user = User::factory()->create(['current_label_id' => $label->id]);
    $label->users()->attach($user, ['role' => LabelRole::Payee->value]);

    $response = $this->actingAs($user)->{$method}(route($route));

    $method === 'get' ? $response->assertRedirect(route('portal.index')) : $response->assertForbidden();
    $this->assertDatabaseCount('bandcamp_connections', 1);
    Http::assertNothingSent();
})->with([
    ['get', 'bandcamp.edit'],
    ['post', 'bandcamp.connect'],
    ['post', 'bandcamp.bands'],
    ['put', 'bandcamp.update'],
    ['post', 'bandcamp.sync'],
    ['delete', 'bandcamp.destroy'],
]);

test('connecting stores the credentials encrypted and loads the accounts', function () {
    $label = Label::factory()->create();
    fakeBandcampConnect();

    $this->actingAs(labelAdmin($label))
        ->from(route('bandcamp.edit'))
        ->post(route('bandcamp.connect'), ['client_id' => ' 4711 ', 'client_secret' => 'example-client-secret'])
        ->assertRedirect(route('bandcamp.edit'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Connected to Bandcamp. Choose the account to import.');

    $connection = BandcampConnection::query()->where('label_id', $label->id)->sole();

    expect($connection->client_id)->toBe('4711')
        ->and($connection->client_secret)->toBe('example-client-secret')
        ->and($connection->refresh_token)->toBe('new-refresh')
        ->and($connection->bands)->toBe(BANDCAMP_BANDS)
        ->and($connection->band_id)->toBeNull()
        ->and($connection->enabled)->toBeFalse()
        ->and(json_encode(DB::table('bandcamp_connections')->first()))->not->toContain('example-client-secret')->not->toContain('new-refresh');
});

test('connecting requires both credentials', function () {
    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->post(route('bandcamp.connect'), [])
        ->assertSessionHasErrors(['client_id' => 'The client id field is required.', 'client_secret' => 'The client secret field is required.']);

    $this->assertDatabaseCount('bandcamp_connections', 0);
    Http::assertNothingSent();
});

test('credentials bandcamp rejects are not stored', function () {
    $label = Label::factory()->create();
    Http::fake([BANDCAMP_TOKEN_URL => Http::response(['error' => 'invalid_client'], 401)]);

    $this->actingAs(labelAdmin($label))
        ->post(route('bandcamp.connect'), ['client_id' => '4711', 'client_secret' => 'wrong'])
        ->assertSessionHasErrors(['bandcamp' => 'Bandcamp did not accept the client id and client secret (invalid_client). Compare them with Settings → API Access on Bandcamp and enter them again.']);

    $this->assertDatabaseCount('bandcamp_connections', 0);
});

test('a duplicate grant while connecting explains how to regenerate the client', function () {
    Http::fake([BANDCAMP_TOKEN_URL => Http::response(['error' => 'duplicate_grant', 'error_description' => 'client 4711 has multiple active grants'], 401)]);

    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->post(route('bandcamp.connect'), ['client_id' => '4711', 'client_secret' => 'example-client-secret'])
        ->assertSessionHasErrors(['bandcamp' => 'Bandcamp says these credentials are already in use somewhere else, and the token that belongs to them is not stored here. On Bandcamp, open Settings → API Access, regenerate the client, then enter the new client id and secret here.']);

    $this->assertDatabaseCount('bandcamp_connections', 0);
});

test('replacement credentials bandcamp rejects leave the working connection as it was', function () {
    $label = Label::factory()->create();
    $connection = BandcampConnection::factory()->create(['label_id' => $label->id]);
    Http::fake([BANDCAMP_TOKEN_URL => Http::response(['error' => 'invalid_client'], 401)]);

    $this->actingAs(labelAdmin($label))
        ->post(route('bandcamp.connect'), ['client_id' => '4712', 'client_secret' => 'typo'])
        ->assertSessionHasErrors('bandcamp');

    $connection->refresh();

    expect($connection->client_id)->toBe('4711')
        ->and($connection->client_secret)->toBe('example-client-secret')
        ->and($connection->refresh_token)->toBe('example-refresh-token')
        ->and($connection->band_id)->toBe(1000001);
});

test('new credentials replace the tokens of the old client and keep the sync settings', function () {
    $label = Label::factory()->create();
    $connection = BandcampConnection::factory()->create(['label_id' => $label->id, 'last_synced_until' => '2026-02-10 12:00:00']);
    fakeBandcampConnect();

    $this->actingAs(labelAdmin($label))
        ->post(route('bandcamp.connect'), ['client_id' => '4712', 'client_secret' => 'regenerated-secret'])
        ->assertSessionHasNoErrors();

    $connection->refresh();

    expect($connection->client_id)->toBe('4712')
        ->and($connection->refresh_token)->toBe('new-refresh')
        ->and($connection->band_id)->toBe(1000001)
        ->and($connection->enabled)->toBeTrue()
        ->and($connection->last_synced_until->toDateTimeString())->toBe('2026-02-10 12:00:00');
    Http::assertSent(fn (Request $request) => $request->url() === BANDCAMP_TOKEN_URL && $request['grant_type'] === 'client_credentials' && $request['client_id'] === '4712');
});

test('reloading the accounts drops a selection bandcamp no longer lists and pauses the sync', function () {
    $label = Label::factory()->create();
    $connection = BandcampConnection::factory()->create(['label_id' => $label->id, 'band_id' => 5000005]);
    Http::fake([BANDCAMP_BANDS_URL => Http::response(['bands' => BANDCAMP_BANDS])]);

    $this->actingAs(labelAdmin($label))
        ->post(route('bandcamp.bands'))
        ->assertSessionHasNoErrors();

    expect($connection->refresh()->band_id)->toBeNull()
        ->and($connection->enabled)->toBeFalse()
        ->and($connection->bands)->toBe(BANDCAMP_BANDS);
});

test('the sync settings can be saved', function () {
    $this->travelTo('2026-02-10 12:00:00');
    $label = Label::factory()->create();
    $connection = BandcampConnection::factory()->create(['label_id' => $label->id, 'bands' => BANDCAMP_BANDS, 'band_id' => null, 'enabled' => false, 'sync_from' => null]);
    $foreign = BandcampConnection::factory()->create(['enabled' => false]);

    $this->actingAs(labelAdmin($label))
        ->from(route('bandcamp.edit'))
        ->put(route('bandcamp.update'), ['band_id' => '1000001', 'member_band_id' => '1000002', 'sync_from' => '2025-07-01', 'enabled' => '1'])
        ->assertRedirect(route('bandcamp.edit'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Bandcamp settings saved.');

    $connection->refresh();

    expect($connection->band_id)->toBe(1000001)
        ->and($connection->member_band_id)->toBe(1000002)
        ->and($connection->sync_from->toDateString())->toBe('2025-07-01')
        ->and($connection->enabled)->toBeTrue()
        ->and($foreign->refresh()->enabled)->toBeFalse();
});

test('invalid sync settings are rejected', function (array $input, string $field, string $message) {
    $this->travelTo('2026-02-10 12:00:00');
    $label = Label::factory()->create();
    $connection = BandcampConnection::factory()->create(['label_id' => $label->id, 'bands' => BANDCAMP_BANDS]);

    $this->actingAs(labelAdmin($label))
        ->put(route('bandcamp.update'), ['band_id' => 1000001, 'sync_from' => '2026-01-01', 'enabled' => true, ...$input])
        ->assertSessionHasErrors([$field => $message]);

    expect($connection->refresh()->sync_from->toDateString())->toBe('2026-01-01');
})->with([
    'an account bandcamp did not list' => [['band_id' => 9999999], 'band_id', 'Choose one of the accounts Bandcamp listed for your credentials.'],
    'an artist of another account' => [['member_band_id' => 9999999], 'member_band_id', 'Choose an artist that belongs to the selected account.'],
    'no start date' => [['sync_from' => ''], 'sync_from', 'The sync from field is required.'],
    'a start date in the future' => [['sync_from' => '2026-02-11'], 'sync_from', 'The sync from field must be a date before or equal to today.'],
]);

test('changing the start date or the account makes the next sync start over', function (array $change) {
    $label = Label::factory()->create();
    $connection = BandcampConnection::factory()->create(['label_id' => $label->id, 'bands' => BANDCAMP_BANDS, 'last_synced_until' => '2026-02-10 12:00:00']);

    $this->actingAs(labelAdmin($label))
        ->put(route('bandcamp.update'), ['band_id' => 1000001, 'member_band_id' => '', 'sync_from' => '2026-01-01', 'enabled' => true, ...$change])
        ->assertSessionHasNoErrors();

    expect($connection->refresh()->last_synced_until)->toBeNull();
})->with([
    'earlier start' => [['sync_from' => '2025-01-01']],
    'one artist only' => [['member_band_id' => 1000002]],
]);

test('saving unchanged settings keeps the sync position', function () {
    $label = Label::factory()->create();
    $connection = BandcampConnection::factory()->create(['label_id' => $label->id, 'bands' => BANDCAMP_BANDS, 'last_synced_until' => '2026-02-10 12:00:00']);

    $this->actingAs(labelAdmin($label))
        ->put(route('bandcamp.update'), ['band_id' => 1000001, 'member_band_id' => '', 'sync_from' => '2026-01-01', 'enabled' => false])
        ->assertSessionHasNoErrors();

    expect($connection->refresh()->last_synced_until->toDateTimeString())->toBe('2026-02-10 12:00:00')
        ->and($connection->enabled)->toBeFalse();
});

test('sync now imports the sales and reports how many', function () {
    Storage::fake('local');
    $this->travelTo('2026-02-10 12:00:00');
    $label = Label::factory()->create();
    $admin = labelAdmin($label);
    BandcampConnection::factory()->create(['label_id' => $label->id, 'sync_from' => '2026-02-01']);
    fakeBandcampSales(['2026-02-01 00:00:00' => [bandcampSale()]]);

    $this->actingAs($admin)
        ->from(route('bandcamp.edit'))
        ->post(route('bandcamp.sync'))
        ->assertRedirect(route('bandcamp.edit'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', '1 transaction imported. Sales are in up to 2026-02-10 12:00 UTC.');

    $this->assertDatabaseHas('transactions', ['label_id' => $label->id, 'external_id' => '900000001']);
    $this->assertDatabaseHas('data_imports', ['label_id' => $label->id, 'user_id' => $admin->id, 'importer' => 'bandcamp']);
});

test('sync now shows what bandcamp said when it fails', function () {
    $label = Label::factory()->create();
    $connection = BandcampConnection::factory()->create(['label_id' => $label->id]);
    Http::fake([BANDCAMP_SALES_URL => Http::response(['error' => true, 'error_message' => 'band_id is not one of yours'])]);

    $this->actingAs(labelAdmin($label))
        ->post(route('bandcamp.sync'))
        ->assertSessionHasErrors(['bandcamp' => 'Bandcamp reported a problem: band_id is not one of yours.']);

    expect($connection->refresh()->last_sync_status)->toBe('failed')
        ->and(Transaction::query()->count())->toBe(0);
});

test('sync now never uses the connection of another label', function () {
    BandcampConnection::factory()->create();

    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->post(route('bandcamp.sync'))
        ->assertNotFound();

    Http::assertNothingSent();
});

test('disconnecting deletes the stored credentials of the current label only', function () {
    $label = Label::factory()->create();
    BandcampConnection::factory()->create(['label_id' => $label->id]);
    $foreign = BandcampConnection::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->from(route('bandcamp.edit'))
        ->delete(route('bandcamp.destroy'))
        ->assertRedirect(route('bandcamp.edit'))
        ->assertInertiaFlash('toast.message', 'Disconnected from Bandcamp. The stored credentials were deleted.');

    expect(DB::table('bandcamp_connections')->pluck('id')->all())->toBe([$foreign->id]);
});

test('sync settings cannot be saved before the label is connected', function () {
    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->put(route('bandcamp.update'), ['band_id' => 1000001, 'sync_from' => '2026-01-01'])
        ->assertSessionHasErrors('band_id');

    $this->assertDatabaseCount('bandcamp_connections', 0);
});
