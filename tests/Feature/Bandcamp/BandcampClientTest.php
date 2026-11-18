<?php

use App\Models\BandcampConnection;
use App\Services\Bandcamp\BandcampClient;
use App\Services\Bandcamp\BandcampException;
use App\Services\Bandcamp\BandcampUnreachableException;
use App\Services\Bandcamp\DuplicateGrantException;
use App\Services\Bandcamp\InvalidCredentialsException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => Http::preventStrayRequests());

test('the first token is requested with the client credentials and stored', function () {
    $this->freezeTime();
    $connection = BandcampConnection::factory()->withoutTokens()->create();
    Http::fake([
        BANDCAMP_TOKEN_URL => Http::response(['ok' => true, 'access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => 3600, 'token_type' => 'bearer']),
        BANDCAMP_BANDS_URL => Http::response(['bands' => []]),
    ]);

    (new BandcampClient($connection))->bands();

    Http::assertSent(fn (Request $request) => $request->url() === BANDCAMP_TOKEN_URL
        && $request->isForm()
        && $request->data() === ['grant_type' => 'client_credentials', 'client_id' => '4711', 'client_secret' => 'example-client-secret']);
    Http::assertSent(fn (Request $request) => $request->url() === BANDCAMP_BANDS_URL && $request->hasHeader('Authorization', 'Bearer new-access'));

    $connection->refresh();

    expect($connection->access_token)->toBe('new-access')
        ->and($connection->refresh_token)->toBe('new-refresh')
        ->and($connection->access_token_expires_at->toDateTimeString())->toBe(now()->addHour()->toDateTimeString());
});

test('a valid access token is used without asking for a new one', function () {
    $connection = BandcampConnection::factory()->create();
    Http::fake([BANDCAMP_BANDS_URL => Http::response(['bands' => []])]);

    (new BandcampClient($connection))->bands();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer example-access-token'));
});

test('an expired access token is renewed with the refresh token and the rotated refresh token is kept', function () {
    $connection = BandcampConnection::factory()->expired()->create();
    Http::fake([
        BANDCAMP_TOKEN_URL => Http::response(['ok' => true, 'access_token' => 'renewed-access', 'refresh_token' => 'rotated-refresh', 'expires_in' => 3600]),
        BANDCAMP_BANDS_URL => Http::response(['bands' => []]),
    ]);

    (new BandcampClient($connection))->bands();

    Http::assertSent(fn (Request $request) => $request->url() === BANDCAMP_TOKEN_URL
        && $request->data() === ['grant_type' => 'refresh_token', 'refresh_token' => 'example-refresh-token', 'client_id' => '4711', 'client_secret' => 'example-client-secret']);
    Http::assertNotSent(fn (Request $request) => $request->url() === BANDCAMP_TOKEN_URL && $request['grant_type'] === 'client_credentials');

    expect($connection->refresh()->refresh_token)->toBe('rotated-refresh')
        ->and($connection->access_token)->toBe('renewed-access');
});

test('the stored refresh token stays when bandcamp does not send a new one', function () {
    $connection = BandcampConnection::factory()->expired()->create();
    Http::fake([
        BANDCAMP_TOKEN_URL => Http::response(['ok' => true, 'access_token' => 'renewed-access', 'expires_in' => 3600]),
        BANDCAMP_BANDS_URL => Http::response(['bands' => []]),
    ]);

    (new BandcampClient($connection))->bands();

    expect($connection->refresh()->refresh_token)->toBe('example-refresh-token');
});

test('a token another process stored in the meantime is used instead of asking again', function () {
    $connection = BandcampConnection::factory()->expired()->create();
    BandcampConnection::query()->whereKey($connection->id)->first()->update(['access_token' => 'fresh-from-elsewhere', 'access_token_expires_at' => now()->addHour()]);
    Http::fake([BANDCAMP_BANDS_URL => Http::response(['bands' => []])]);

    (new BandcampClient($connection))->bands();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer fresh-from-elsewhere'));
});

test('a duplicate grant tells the admin to regenerate the client', function () {
    $connection = BandcampConnection::factory()->withoutTokens()->create();
    Http::fake([
        BANDCAMP_TOKEN_URL => Http::response(['error' => 'duplicate_grant', 'error_description' => 'client 4711 has multiple active grants'], 401),
    ]);

    expect(fn () => (new BandcampClient($connection))->bands())
        ->toThrow(DuplicateGrantException::class, 'regenerate the client');

    expect($connection->refresh()->access_token)->toBeNull();
});

test('a rejected refresh token falls back to the client credentials and keeps the token when that is a duplicate grant', function () {
    $connection = BandcampConnection::factory()->expired()->create();
    Http::fake([
        BANDCAMP_TOKEN_URL => fn (Request $request) => $request['grant_type'] === 'refresh_token'
            ? Http::response(['error' => 'invalid_grant'], 400)
            : Http::response(['error' => 'duplicate_grant'], 401),
    ]);

    expect(fn () => (new BandcampClient($connection))->bands())->toThrow(DuplicateGrantException::class);

    expect($connection->refresh()->refresh_token)->toBe('example-refresh-token');
});

test('wrong credentials are reported as such', function () {
    $connection = BandcampConnection::factory()->withoutTokens()->create();
    Http::fake([BANDCAMP_TOKEN_URL => Http::response(['error' => 'invalid_client', 'error_description' => 'bad client secret'], 401)]);

    expect(fn () => (new BandcampClient($connection))->bands())
        ->toThrow(InvalidCredentialsException::class, 'Bandcamp did not accept the client id and client secret (bad client secret)');
});

test('a 401 from the api renews the token once and repeats the call', function () {
    $connection = BandcampConnection::factory()->create();
    Http::fake([
        BANDCAMP_TOKEN_URL => Http::response(['ok' => true, 'access_token' => 'renewed-access', 'refresh_token' => 'rotated-refresh', 'expires_in' => 3600]),
        BANDCAMP_BANDS_URL => fn (Request $request) => $request->hasHeader('Authorization', 'Bearer renewed-access')
            ? Http::response(['bands' => [['band_id' => 1000001, 'name' => 'Example Records', 'subdomain' => 'example-records', 'member_bands' => [['band_id' => 1000002, 'name' => 'Example Artist', 'subdomain' => 'example-artist']]]]])
            : Http::response(['error' => 'invalid_token'], 401),
    ]);

    $bands = (new BandcampClient($connection))->bands();

    expect($bands)->toBe([[
        'band_id' => 1000001, 'name' => 'Example Records', 'subdomain' => 'example-records',
        'member_bands' => [['band_id' => 1000002, 'name' => 'Example Artist', 'subdomain' => 'example-artist']],
    ]])->and($connection->refresh()->refresh_token)->toBe('rotated-refresh');
    Http::assertSentCount(3);
});

test('a second 401 after renewing is not retried forever', function () {
    $connection = BandcampConnection::factory()->create();
    Http::fake([
        BANDCAMP_TOKEN_URL => Http::response(['ok' => true, 'access_token' => 'renewed-access', 'expires_in' => 3600]),
        BANDCAMP_BANDS_URL => Http::response(['error' => 'invalid_token'], 401),
    ]);

    expect(fn () => (new BandcampClient($connection))->bands())->toThrow(BandcampException::class, 'Bandcamp refused the request');
    Http::assertSentCount(3);
});

test('the sales report is requested for the band and the window in utc', function () {
    $connection = BandcampConnection::factory()->create();
    Http::fake([BANDCAMP_SALES_URL => Http::response(['report' => [bandcampSale()]])]);

    $report = (new BandcampClient($connection))->salesReport(1000001, now()->setDate(2026, 2, 1)->startOfDay(), now()->setDate(2026, 3, 1)->startOfDay(), 1000002);

    expect($report)->toHaveCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === BANDCAMP_SALES_URL
        && $request->isJson()
        && $request->data() === ['band_id' => 1000001, 'member_band_id' => 1000002, 'start_time' => '2026-02-01 00:00:00', 'end_time' => '2026-03-01 00:00:00']);
});

test('a server error is retried and then succeeds', function () {
    Sleep::fake();
    $connection = BandcampConnection::factory()->create();
    Http::fake([BANDCAMP_SALES_URL => Http::sequence()->pushStatus(503)->push(['report' => []])]);

    expect((new BandcampClient($connection))->salesReport(1000001, now()->subDay(), now()))->toBe([]);
    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

test('a connection failure that outlasts the retries is reported as bandcamp being unreachable', function () {
    Sleep::fake();
    $connection = BandcampConnection::factory()->create();
    Http::fake([BANDCAMP_SALES_URL => Http::failedConnection()]);

    expect(fn () => (new BandcampClient($connection))->salesReport(1000001, now()->subDay(), now()))
        ->toThrow(BandcampUnreachableException::class, 'Bandcamp could not be reached');
});

test('an error bandcamp reports in the body is passed on in its own words', function () {
    $connection = BandcampConnection::factory()->create();
    Http::fake([BANDCAMP_SALES_URL => Http::response(['error' => true, 'error_message' => 'band_id is not one of yours'])]);

    expect(fn () => (new BandcampClient($connection))->salesReport(1000001, now()->subDay(), now()))
        ->toThrow(BandcampException::class, 'Bandcamp reported a problem: band_id is not one of yours.');
});

test('credentials and tokens are encrypted in the database and hidden when serialised', function () {
    $connection = BandcampConnection::factory()->create();

    $stored = (array) DB::table('bandcamp_connections')->where('id', $connection->id)->first();

    expect(json_encode($stored))->not->toContain('4711')
        ->not->toContain('example-client-secret')
        ->not->toContain('example-refresh-token')
        ->not->toContain('example-access-token')
        ->and(array_keys($connection->toArray()))->not->toContain('client_id', 'client_secret', 'refresh_token', 'access_token');
});
