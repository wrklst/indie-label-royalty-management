<?php

use App\Enums\ImportStatus;
use App\Models\Artist;
use App\Models\BandcampConnection;
use App\Models\DataImport;
use App\Models\Label;
use App\Models\Release;
use App\Models\Track;
use App\Models\Transaction;
use App\Services\Bandcamp\BandcampException;
use App\Services\Bandcamp\BandcampSync;
use App\Services\Bandcamp\BandcampUnreachableException;
use App\Services\Imports\Importers\BandcampImporter;
use App\Services\Imports\ImportRunner;
use App\Services\Imports\Row;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => Http::preventStrayRequests());

/**
 * A label connected to Bandcamp, syncing from the given day.
 */
function connectedLabel(string $syncFrom, array $labelAttributes = []): BandcampConnection
{
    Storage::fake('local');

    return BandcampConnection::factory()->create([
        'label_id' => Label::factory()->create(['currency' => 'EUR', ...$labelAttributes]),
        'sync_from' => $syncFrom,
    ]);
}

test('a sync imports the sales with the one percent fee deducted and builds the catalog', function () {
    $this->travelTo('2026-02-10 12:00:00');
    $connection = connectedLabel('2026-02-01');
    fakeBandcampSales(['2026-02-01 00:00:00' => [
        bandcampSale(),
        bandcampSale([
            'bandcamp_transaction_id' => 900000002, 'bandcamp_transaction_item_id' => 700000002, 'date' => '05 Feb 2026 08:00:00 GMT',
            'item_type' => 'track', 'item_name' => 'Example Track', 'net_amount' => 1.14, 'isrc' => 'ZZTST2600001', 'country_code' => 'DE',
        ]),
    ]]);

    $result = app(BandcampSync::class)->run($connection);

    expect($result->transactions)->toBe(2)->and($result->imports)->toBe(1)->and($result->complete)->toBeTrue();

    $import = DataImport::query()->sole();
    $album = Transaction::query()->where('format', 'Download Album')->sole();
    $track = Transaction::query()->where('format', 'Download Track')->sole();

    expect($import->name)->toBe('Bandcamp sync 2026-02-01 – 2026-02-10')
        ->and($import->label_id)->toBe($connection->label_id)
        ->and($import->importer)->toBe('bandcamp')
        ->and($import->status)->toBe(ImportStatus::Imported)
        ->and($album->label_id)->toBe($connection->label_id)
        ->and($album->total)->toBe('8.415000000000000')
        ->and($album->timestamp->toDateString())->toBe('2026-02-04')
        ->and($album->external_id)->toBe('900000001')
        ->and($album->importer_source)->toBe('bandcamp')
        ->and($album->region)->toBe('AT')
        ->and($album->release->name)->toBe('Example Album')
        ->and($album->release->catalog_number)->toBe('EX001')
        ->and($track->total)->toBe('1.128600000000000')
        ->and($track->track->isrc)->toBe('ZZTST2600001')
        ->and(Artist::query()->sole()->name)->toBe('Example Artist')
        ->and(Release::query()->count())->toBe(1)
        ->and(Track::query()->count())->toBe(1);

    $connection->refresh();

    expect($connection->last_synced_until->toDateTimeString())->toBe('2026-02-10 12:00:00')
        ->and($connection->last_sync_status)->toBe('ok')
        ->and($connection->last_sync_message)->toContain('2 transactions imported');
});

test('the fee deduction and the physical exclusion follow the label settings', function () {
    $this->travelTo('2026-02-10 12:00:00');
    $connection = connectedLabel('2026-02-01', ['importer_settings' => ['bandcamp' => ['disable_fee' => true, 'exclude_physical' => true]]]);
    fakeBandcampSales(['2026-02-01 00:00:00' => [
        bandcampSale(),
        bandcampSale(['bandcamp_transaction_id' => 900000002, 'bandcamp_transaction_item_id' => 700000002, 'item_type' => 'package', 'item_name' => 'Example Album LP', 'net_amount' => 20.0]),
    ]]);

    app(BandcampSync::class)->run($connection);

    expect(Transaction::query()->sole()->total)->toBe('8.500000000000000');
});

test('physical sales are imported unless the label excludes them', function () {
    $this->travelTo('2026-02-10 12:00:00');
    $connection = connectedLabel('2026-02-01');
    fakeBandcampSales(['2026-02-01 00:00:00' => [
        bandcampSale(['item_type' => 'package', 'item_name' => 'Example Album LP', 'net_amount' => 20.0]),
    ]]);

    app(BandcampSync::class)->run($connection);

    expect(Transaction::query()->sole())
        ->format->toBe('Physical/Other')
        ->total->toBe('19.800000000000000');
});

test('payouts are left out and a window without sales creates no import', function () {
    $this->travelTo('2026-02-10 12:00:00');
    $connection = connectedLabel('2026-02-01');
    fakeBandcampSales(['2026-02-01 00:00:00' => [
        bandcampSale(['item_type' => 'payout', 'item_name' => null, 'net_amount' => null, 'bandcamp_transaction_id' => 900000009]),
    ]]);

    $result = app(BandcampSync::class)->run($connection);

    expect($result->transactions)->toBe(0)
        ->and(DataImport::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and($connection->refresh()->last_synced_until->toDateTimeString())->toBe('2026-02-10 12:00:00');
});

test('a first sync over several months asks month by month and creates one import per month with sales', function () {
    $this->travelTo('2026-04-10 12:00:00');
    $connection = connectedLabel('2026-01-15');
    fakeBandcampSales([
        '2026-01-15 00:00:00' => [bandcampSale(['date' => '20 Jan 2026 10:00:00 GMT'])],
        '2026-03-15 00:00:00' => [bandcampSale(['bandcamp_transaction_id' => 900000003, 'bandcamp_transaction_item_id' => 700000003, 'date' => '20 Mar 2026 10:00:00 GMT'])],
    ]);

    $result = app(BandcampSync::class)->run($connection);

    $windows = Http::recorded()->map(fn (array $pair): array => [$pair[0]['start_time'], $pair[0]['end_time']])->all();

    expect($windows)->toBe([
        ['2026-01-15 00:00:00', '2026-02-15 00:00:00'],
        ['2026-02-15 00:00:00', '2026-03-15 00:00:00'],
        ['2026-03-15 00:00:00', '2026-04-10 12:00:00'],
    ])->and($result->windows)->toBe(3)
        ->and(DataImport::query()->orderBy('id')->pluck('name')->all())->toBe([
            'Bandcamp sync 2026-01-15 – 2026-02-15',
            'Bandcamp sync 2026-03-15 – 2026-04-10',
        ])
        ->and(Transaction::query()->count())->toBe(2);
});

test('the next sync continues where the last one stopped', function () {
    $this->travelTo('2026-02-10 12:00:00');
    $connection = connectedLabel('2026-02-01');
    fakeBandcampSales([]);
    app(BandcampSync::class)->run($connection);

    $this->travelTo('2026-02-10 12:30:00');
    app(BandcampSync::class)->run($connection);

    expect(Http::recorded()->last()[0]->data())->toMatchArray(['start_time' => '2026-02-10 12:00:00', 'end_time' => '2026-02-10 12:30:00']);
});

test('a sale fetched again in an overlapping window is not imported twice', function () {
    $this->travelTo('2026-02-10 12:00:00');
    $connection = connectedLabel('2026-02-01');
    Http::fake([BANDCAMP_SALES_URL => Http::response(['report' => [
        bandcampSale(),
        bandcampSale(['bandcamp_transaction_item_id' => 700000002, 'item_type' => 'track', 'item_name' => 'Example Track', 'net_amount' => 1.0]),
    ]])]);
    app(BandcampSync::class)->run($connection);

    $connection->update(['last_synced_until' => null]);
    $this->travelTo('2026-02-10 12:30:00');
    $result = app(BandcampSync::class)->run($connection);

    expect($result->transactions)->toBe(0)
        ->and($result->alreadyImported)->toBe(2)
        ->and(Transaction::query()->count())->toBe(2)
        ->and(DataImport::query()->count())->toBe(1);
});

test('a refund with its own order id is imported although it names the same item', function () {
    $this->travelTo('2026-02-10 12:00:00');
    $connection = connectedLabel('2026-02-01');
    fakeBandcampSales(['2026-02-01 00:00:00' => [
        bandcampSale(),
        bandcampSale(['bandcamp_transaction_id' => 900000050, 'bandcamp_related_transaction_id' => 900000001, 'net_amount' => -8.5]),
    ]]);

    app(BandcampSync::class)->run($connection);

    expect(Transaction::query()->orderBy('id')->pluck('total')->all())->toBe(['8.415000000000000', '-8.415000000000000']);
});

test('a sale that was imported from an uploaded bandcamp report before is not imported again', function () {
    $this->travelTo('2023-08-20 12:00:00');
    $connection = connectedLabel('2023-08-01');
    $label = Label::query()->findOrFail($connection->label_id);
    Storage::disk('local')->put('imports/manual.csv', file_get_contents(base_path('tests/Fixtures/bandcamp_raw_sample.csv')));
    app(ImportRunner::class)->run(DataImport::factory()->create([
        'label_id' => $label->id, 'importer' => 'bandcamp', 'status' => 'pending', 'file_path' => 'imports/manual.csv',
    ]), $label);

    fakeBandcampSales(['2023-08-01 00:00:00' => [
        bandcampSale(['bandcamp_transaction_id' => 577718015, 'bandcamp_transaction_item_id' => 700000010, 'date' => '03 Aug 2023 21:15:00 GMT', 'item_type' => 'track', 'item_name' => 'Example Track (Remix)', 'net_amount' => 1.14]),
        bandcampSale(['bandcamp_transaction_id' => 577718099, 'bandcamp_transaction_item_id' => 700000011, 'date' => '05 Aug 2023 11:02:00 GMT', 'net_amount' => 5.6]),
        bandcampSale(['bandcamp_transaction_id' => 577718099, 'bandcamp_transaction_item_id' => 700000012, 'date' => '05 Aug 2023 11:02:00 GMT', 'item_type' => 'track', 'item_name' => 'Example Bonus Track', 'net_amount' => 1.0]),
    ]]);

    $result = app(BandcampSync::class)->run($connection);

    expect($result->alreadyImported)->toBe(2)
        ->and($result->transactions)->toBe(1)
        ->and(Transaction::query()->count())->toBe(3)
        ->and(Transaction::query()->where('external_id', '577718099')->count())->toBe(2)
        ->and(Transaction::query()->where('external_id', '577718015')->count())->toBe(1);
});

test('a bandcamp report uploaded after a sync does not book the synced sales again', function () {
    $this->travelTo('2023-08-20 12:00:00');
    $connection = connectedLabel('2023-08-01');
    $label = Label::query()->findOrFail($connection->label_id);
    fakeBandcampSales(['2023-08-01 00:00:00' => [
        bandcampSale(['bandcamp_transaction_id' => 577718015, 'bandcamp_transaction_item_id' => 700000010, 'date' => '03 Aug 2023 21:15:00 GMT', 'item_type' => 'track', 'item_name' => 'Example Track (Remix)', 'net_amount' => 1.14]),
        bandcampSale(['bandcamp_transaction_id' => 577718099, 'bandcamp_transaction_item_id' => 700000011, 'date' => '05 Aug 2023 11:02:00 GMT', 'net_amount' => 5.6]),
    ]]);
    app(BandcampSync::class)->run($connection);

    Storage::disk('local')->put('imports/manual.csv', file_get_contents(base_path('tests/Fixtures/bandcamp_raw_sample.csv')));
    $upload = app(ImportRunner::class)->run(DataImport::factory()->create([
        'label_id' => $label->id, 'importer' => 'bandcamp', 'status' => 'pending', 'file_path' => 'imports/manual.csv',
    ]), $label);

    expect($upload->status)->toBe(ImportStatus::Imported)
        ->and($upload->transactions_count)->toBe(0)
        ->and(Transaction::query()->count())->toBe(2);
});

/**
 * A Bandcamp sale the way it arrives from another royalty system: with
 * the order id and the format, but without the line Bandcamp sent.
 */
function migratedBandcampSale(int $labelId, string $orderId, string $format, string $source = 'bandcamp_api'): Transaction
{
    return Transaction::factory()->create([
        'label_id' => $labelId, 'importer_source' => $source, 'external_id' => $orderId, 'format' => $format,
        'timestamp' => '2023-08-05', 'paid_timestamp' => '2023-08-05', 'raw_data' => ['infinite_catalog_id' => fake()->randomNumber(6)],
    ]);
}

test('sales carried over from another royalty system are not imported again by a sync that starts before them', function () {
    $this->travelTo('2023-08-20 12:00:00');
    $connection = connectedLabel('2023-08-01');
    migratedBandcampSale($connection->label_id, '577718015', 'Download Track');
    migratedBandcampSale($connection->label_id, '577718099', 'Download Album', 'bandcamp');
    migratedBandcampSale($connection->label_id, '577718099', 'Download Track');

    fakeBandcampSales(['2023-08-01 00:00:00' => [
        bandcampSale(['bandcamp_transaction_id' => 577718015, 'bandcamp_transaction_item_id' => 700000010, 'item_type' => 'track', 'item_name' => 'Example Track (Remix)', 'net_amount' => 1.14]),
        bandcampSale(['bandcamp_transaction_id' => 577718099, 'bandcamp_transaction_item_id' => 700000011, 'net_amount' => 5.6]),
        bandcampSale(['bandcamp_transaction_id' => 577718099, 'bandcamp_transaction_item_id' => 700000012, 'item_type' => 'track', 'item_name' => 'Example Bonus Track', 'net_amount' => 1.0]),
        // A second track of the same order that the other system never had, and a sale it never had at all.
        bandcampSale(['bandcamp_transaction_id' => 577718099, 'bandcamp_transaction_item_id' => 700000013, 'item_type' => 'track', 'item_name' => 'Example Hidden Track', 'net_amount' => 1.0]),
        bandcampSale(['bandcamp_transaction_id' => 577718500, 'bandcamp_transaction_item_id' => 700000014, 'item_type' => 'track', 'item_name' => 'Example Track', 'net_amount' => 1.0]),
    ]]);

    $result = app(BandcampSync::class)->run($connection);

    expect($result->alreadyImported)->toBe(3)
        ->and($result->transactions)->toBe(2)
        ->and(Transaction::query()->count())->toBe(5)
        ->and(Transaction::query()->where('external_id', '577718015')->count())->toBe(1)
        ->and(Transaction::query()->where('external_id', '577718099')->count())->toBe(3)
        ->and(Transaction::query()->where('external_id', '577718500')->count())->toBe(1);

    // Syncing the same month again changes nothing.
    $connection->update(['last_synced_until' => null]);

    expect(app(BandcampSync::class)->run($connection)->transactions)->toBe(0)
        ->and(Transaction::query()->count())->toBe(5);
});

test('a bandcamp report uploaded after moving from another royalty system does not book its sales again', function () {
    $connection = connectedLabel('2023-08-01');
    $label = Label::query()->findOrFail($connection->label_id);
    migratedBandcampSale($label->id, '577718015', 'Download Track');

    Storage::disk('local')->put('imports/manual.csv', file_get_contents(base_path('tests/Fixtures/bandcamp_raw_sample.csv')));
    $upload = app(ImportRunner::class)->run(DataImport::factory()->create([
        'label_id' => $label->id, 'importer' => 'bandcamp', 'status' => 'pending', 'file_path' => 'imports/manual.csv',
    ]), $label);

    expect($upload->status)->toBe(ImportStatus::Imported)
        ->and($upload->transactions_count)->toBe(1)
        ->and(Transaction::query()->count())->toBe(2)
        ->and(Transaction::query()->where('data_import_id', $upload->id)->sole()->external_id)->toBe('577718099');
});

test('a failing month stops the sync and leaves the cursor at the last good month', function () {
    Sleep::fake();
    $this->travelTo('2026-04-10 12:00:00');
    $connection = connectedLabel('2026-01-15');
    Http::fake([BANDCAMP_SALES_URL => fn (Request $request) => match ($request['start_time']) {
        '2026-01-15 00:00:00' => Http::response(['report' => [bandcampSale(['date' => '20 Jan 2026 10:00:00 GMT'])]]),
        default => Http::response('', 503),
    }]);

    expect(fn () => app(BandcampSync::class)->run($connection))->toThrow(BandcampUnreachableException::class);

    $connection->refresh();

    expect($connection->last_synced_until->toDateTimeString())->toBe('2026-02-15 00:00:00')
        ->and($connection->last_sync_status)->toBe('failed')
        ->and($connection->last_sync_message)->toContain('Bandcamp could not be reached')
        ->and(Transaction::query()->count())->toBe(1);
    Http::assertNotSent(fn (Request $request) => $request['start_time'] === '2026-03-15 00:00:00');
});

test('a month whose import fails does not advance the cursor', function () {
    $this->travelTo('2026-02-10 12:00:00');
    $connection = connectedLabel('2026-02-01');
    fakeBandcampSales(['2026-02-01 00:00:00' => [bandcampSale()]]);
    DB::statement('CREATE TRIGGER reject_transactions BEFORE INSERT ON transactions BEGIN SELECT RAISE(ABORT, "ledger closed"); END');

    expect(fn () => app(BandcampSync::class)->run($connection))
        ->toThrow(BandcampException::class, 'Sales from 2026-02-01 to 2026-02-10 could not be imported');

    expect($connection->refresh()->last_synced_until)->toBeNull()
        ->and($connection->last_sync_status)->toBe('failed')
        ->and(DataImport::query()->sole()->status)->toBe(ImportStatus::Failed)
        ->and(Transaction::query()->count())->toBe(0);
});

test('a time budget stops after the running month and reports that more is waiting', function () {
    $this->travelTo('2026-04-10 12:00:00');
    $connection = connectedLabel('2026-01-15');
    fakeBandcampSales([]);

    $result = app(BandcampSync::class)->run($connection, timeBudget: 0);

    expect($result->complete)->toBeFalse()
        ->and($result->windows)->toBe(1)
        ->and($result->summary())->toContain('sync again to continue')
        ->and($connection->refresh()->last_synced_until->toDateTimeString())->toBe('2026-02-15 00:00:00');
});

test('a connection without an account or start date is not synced', function () {
    $connection = connectedLabel('2026-02-01');
    $connection->update(['band_id' => null]);

    expect(fn () => app(BandcampSync::class)->run($connection))->toThrow(BandcampException::class, 'Choose the Bandcamp account');
});

test('buyer data reaches neither the database nor the stored file', function () {
    $this->travelTo('2026-02-10 12:00:00');
    $connection = connectedLabel('2026-02-01');
    fakeBandcampSales(['2026-02-01 00:00:00' => [bandcampSale(['item_type' => 'package', 'item_name' => 'Example Album LP'])]]);

    app(BandcampSync::class)->run($connection);

    $file = Storage::disk('local')->get(DataImport::query()->sole()->file_path);
    $stored = $file.json_encode(DB::table('transactions')->get()).json_encode(DB::table('data_imports')->get());

    expect($file)->toContain('Example Album LP');

    foreach (['Jane Example', 'jane@example.com', '555 0100', 'Greetings from Jane', 'Example Street', 'Top 5', 'Vienna', 'Wien', '1010', 'Ring twice', 'label@example.com', 'PAYPAL-EXAMPLE-1'] as $personal) {
        expect($stored)->not->toContain($personal);
    }
});

test('no column the importer treats as personal is written to the file', function () {
    $personal = array_map(Row::normalize(...), (new ReflectionClassConstant(BandcampImporter::class, 'PERSONAL_COLUMNS'))->getValue());

    expect(array_intersect(array_map(Row::normalize(...), BandcampSync::COLUMNS), $personal))->toBe([]);
});

test('a sync only writes to its own label', function () {
    $this->travelTo('2026-02-10 12:00:00');
    $connection = connectedLabel('2026-02-01');
    $other = Label::factory()->create();
    $foreign = Transaction::factory()->create(['label_id' => $other->id, 'importer_source' => 'bandcamp', 'external_id' => '900000001', 'raw_data' => ['bandcamp_transaction_id' => '900000001', 'bandcamp_transaction_item_id' => '700000001']]);
    fakeBandcampSales(['2026-02-01 00:00:00' => [bandcampSale()]]);

    app(BandcampSync::class)->run($connection);

    expect(Transaction::query()->where('label_id', $connection->label_id)->count())->toBe(1)
        ->and(DB::table('transactions')->where('label_id', $other->id)->pluck('id')->all())->toBe([$foreign->id])
        ->and(DB::table('releases')->where('label_id', $other->id)->count())->toBe(0);
});
