<?php

use App\Enums\ImportStatus;
use App\Models\Artist;
use App\Models\DataImport;
use App\Models\Label;
use App\Models\Release;
use App\Models\Track;
use App\Models\Transaction;
use App\Services\Imports\ImportRunner;
use Illuminate\Support\Facades\Storage;

/**
 * Import the given CSV text with the spreadsheet template importer.
 */
function importTemplate(Label $label, string $csv, array $attributes = []): DataImport
{
    Storage::fake('local');
    Storage::disk('local')->put('imports/template.csv', $csv);

    return app(ImportRunner::class)->run(DataImport::factory()->create([
        'label_id' => $label->id, 'importer' => 'template', 'status' => 'pending', 'file_path' => 'imports/template.csv',
        ...$attributes,
    ]), $label);
}

test('a line without a date is booked once per period it is imported for', function () {
    $label = currentLabel();
    $csv = "total,release name\n12.50,Example Release\n";

    importTemplate($label, $csv, ['default_timestamp' => '2026-03-31']);
    $sameQuarter = importTemplate($label, $csv, ['default_timestamp' => '2026-03-31']);
    $nextQuarter = importTemplate($label, $csv, ['default_timestamp' => '2026-06-30']);

    expect($sameQuarter->duplicates_count)->toBe(1)
        ->and($nextQuarter->transactions_count)->toBe(1)
        ->and(Transaction::query()->count())->toBe(2);
});

test('a file in two foreign currencies is refused when it comes with one conversion rate', function () {
    $label = currentLabel(['currency' => 'EUR']);

    $import = importTemplate($label, "date,total,currency\n2026-03-31,10,USD\n2026-03-31,10,GBP\n", ['options' => ['conversion_rate' => '0.9']]);

    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->errors[0]['message'])->toContain('USD and GBP')
        ->and(Transaction::query()->count())->toBe(0);
});

test('a statement completes a release and a track that were entered without upc and isrc', function () {
    $label = currentLabel();
    $release = Release::factory()->create(['label_id' => $label->id, 'catalog_number' => 'EX001', 'upc' => null]);
    $track = Track::factory()->create(['label_id' => $label->id, 'release_id' => $release->id, 'name' => 'Example Track', 'isrc' => null]);

    $import = importTemplate($label, "date,total,catalog number,upc,track name,isrc\n2026-03-31,10,EX001,4000000000017,Example Track,ZZTST2600001\n");

    expect($import->created_releases_count)->toBe(0)
        ->and($import->created_tracks_count)->toBe(0)
        ->and($release->refresh()->upc)->toBe('4000000000017')
        ->and($track->refresh()->isrc)->toBe('ZZTST2600001')
        ->and(Transaction::query()->sole()->track_id)->toBe($track->id);
});

test('a release that has another upc is not taken for the one a statement names', function () {
    $label = currentLabel();
    Release::factory()->create(['label_id' => $label->id, 'catalog_number' => 'EX001', 'upc' => '4000000000024']);

    $import = importTemplate($label, "date,total,catalog number,upc\n2026-03-31,10,EX001,4000000000017\n");

    expect($import->created_releases_count)->toBe(1);
});

test('a release and a track a statement names under an alias belong to the main artist', function () {
    $label = currentLabel();
    $main = Artist::factory()->create(['label_id' => $label->id, 'name' => 'Example Artist']);
    Artist::factory()->create(['label_id' => $label->id, 'name' => 'Example Artist feat. Guest', 'alias_of_id' => $main->id]);

    importTemplate($label, "date,total,artist,release name,track name,isrc\n2026-03-31,10,example artist FEAT. guest,Example Release,Example Track,ZZTST2600001\n");

    expect(Track::query()->sole()->artist_id)->toBe($main->id)
        ->and(Release::query()->sole()->artist_id)->toBe($main->id)
        ->and(Artist::query()->count())->toBe(2);
});
