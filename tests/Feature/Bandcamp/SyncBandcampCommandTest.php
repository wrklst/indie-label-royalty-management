<?php

use App\Models\BandcampConnection;
use App\Models\Label;
use App\Models\Transaction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => Http::preventStrayRequests());

test('the command syncs every label with an enabled connection and reports each', function () {
    Storage::fake('local');
    $this->travelTo('2026-02-10 12:00:00');
    $first = BandcampConnection::factory()->create(['label_id' => Label::factory()->create(['name' => 'Example Records']), 'band_id' => 1000001, 'sync_from' => '2026-02-01']);
    $second = BandcampConnection::factory()->create(['label_id' => Label::factory()->create(['name' => 'Second Records']), 'band_id' => 2000002, 'sync_from' => '2026-02-01']);
    $disabled = BandcampConnection::factory()->create(['label_id' => Label::factory()->create(['name' => 'Paused Records']), 'band_id' => 3000003, 'enabled' => false]);
    Http::fake([BANDCAMP_SALES_URL => fn (Request $request) => Http::response(['report' => $request['band_id'] === 1000001 ? [bandcampSale()] : []])]);

    $this->artisan('royalties:sync-bandcamp')
        ->expectsOutputToContain('Example Records: 1 transaction imported')
        ->expectsOutputToContain('Second Records: 0 transactions imported')
        ->doesntExpectOutputToContain('Paused Records')
        ->assertSuccessful();

    expect(Transaction::query()->where('label_id', $first->label_id)->count())->toBe(1)
        ->and(Transaction::query()->where('label_id', $second->label_id)->count())->toBe(0)
        ->and($disabled->refresh()->last_sync_at)->toBeNull();
    Http::assertNotSent(fn (Request $request) => $request['band_id'] === 3000003);
});

test('one failing label does not stop the others and the command fails', function () {
    Storage::fake('local');
    $this->travelTo('2026-02-10 12:00:00');
    BandcampConnection::factory()->create(['label_id' => Label::factory()->create(['name' => 'Example Records']), 'band_id' => 1000001, 'sync_from' => '2026-02-01']);
    $second = BandcampConnection::factory()->create(['label_id' => Label::factory()->create(['name' => 'Second Records']), 'band_id' => 2000002, 'sync_from' => '2026-02-01']);
    Http::fake([BANDCAMP_SALES_URL => fn (Request $request) => $request['band_id'] === 1000001
        ? Http::response(['error' => true, 'error_message' => 'band_id is not one of yours'])
        : Http::response(['report' => [bandcampSale()]])]);

    $this->artisan('royalties:sync-bandcamp')
        ->expectsOutputToContain('Example Records: Bandcamp reported a problem: band_id is not one of yours.')
        ->expectsOutputToContain('Second Records: 1 transaction imported')
        ->assertFailed();

    expect(Transaction::query()->where('label_id', $second->label_id)->count())->toBe(1);
});

test('the command syncs a single label by slug even when its weekly sync is off', function () {
    Storage::fake('local');
    $this->travelTo('2026-02-10 12:00:00');
    BandcampConnection::factory()->create(['label_id' => Label::factory()->create(['slug' => 'example']), 'enabled' => false, 'sync_from' => '2026-02-01']);
    BandcampConnection::factory()->create(['band_id' => 2000002]);
    fakeBandcampSales([]);

    $this->artisan('royalties:sync-bandcamp', ['--label' => 'example'])->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request['band_id'] === 1000001);
});

test('the command fails for an unknown label and for a label without a connection', function () {
    Label::factory()->create(['slug' => 'example', 'name' => 'Example Records']);

    $this->artisan('royalties:sync-bandcamp', ['--label' => 'missing'])
        ->expectsOutputToContain('Label not found')
        ->assertFailed();
    $this->artisan('royalties:sync-bandcamp', ['--label' => 'example'])
        ->expectsOutputToContain('Example Records has no Bandcamp connection')
        ->assertFailed();
});

test('the command succeeds quietly when no label is connected', function () {
    $this->artisan('royalties:sync-bandcamp')
        ->expectsOutputToContain('No label has an enabled Bandcamp connection.')
        ->assertSuccessful();
});

test('the sync is scheduled weekly', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('royalties:sync-bandcamp');
});

test('the command skips a label that switched Bandcamp off under Connections', function () {
    Storage::fake('local');
    $this->travelTo('2026-02-10 12:00:00');
    BandcampConnection::factory()->create([
        'label_id' => Label::factory()->create(['name' => 'Off Records', 'delivery_settings' => ['distributors' => ['bandcamp' => false]]]),
        'band_id' => 3000003, 'sync_from' => '2026-02-01',
    ]);

    $this->artisan('royalties:sync-bandcamp')
        ->expectsOutputToContain('Off Records: Bandcamp is switched off.')
        ->assertSuccessful();

    Http::assertNothingSent();
});
