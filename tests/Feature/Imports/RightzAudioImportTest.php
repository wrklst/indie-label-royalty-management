<?php

use App\Enums\AirplayOrigin;
use App\Models\Airplay;
use App\Models\DataImport;
use App\Models\Label;
use App\Models\Track;
use App\Models\Transaction;
use App\Services\Imports\ImporterRegistry;
use App\Services\Imports\ImportRunner;
use App\Services\Imports\Readers\ReaderFactory;
use Illuminate\Support\Facades\Storage;

const RIGHTZ_HEADER = 'Statement Name,Recording Display Artist,Recording Display Title,Recording Display Title Version,ISRC,Country Code,CMO,Total Client [€],Source Attributes,Allocation Period,Usage Period';

/**
 * One line of a Rightz Audio statement; GVL lines name the broadcaster.
 */
function rightzLine(string $total, ?string $station, string $usage = '[2025-01-01,2026-01-01)', string $allocation = '[2025-01-01,2026-01-01)'): string
{
    $attributes = $station === null ? '{}' : '"{""media_type"": ""Radioprogramm"", ""broadcaster"": ""'.$station.'""}"';

    return "Example Statement,Example Artist,Example Track,,ZZTST2600001,DE,Example Society,{$total},{$attributes},\"{$allocation}\",\"{$usage}\"";
}

/**
 * Import the given lines as a Rightz Audio statement.
 */
function importRightz(Label $label, string ...$lines): DataImport
{
    Storage::disk('local')->put('imports/rightz.csv', RIGHTZ_HEADER."\n".implode("\n", $lines)."\n");

    return app(ImportRunner::class)->run(DataImport::factory()->create([
        'label_id' => $label->id, 'importer' => 'rightz-audio', 'status' => 'pending', 'file_path' => 'imports/rightz.csv',
        'default_paid_timestamp' => '2026-11-30',
    ]), $label);
}

test('a rightz audio statement is recognised from its columns', function () {
    Storage::disk('local')->put('imports/rightz.csv', RIGHTZ_HEADER."\n".rightzLine('1.50', 'Example Radio')."\n");

    $importer = app(ImporterRegistry::class)->resolve('auto', app(ReaderFactory::class)->open(Storage::disk('local')->path('imports/rightz.csv')));

    expect($importer->key())->toBe('rightz-audio');
});

test('a statement line becomes rights income dated at the end of its allocation period', function () {
    $label = currentLabel(['currency' => 'EUR']);
    $track = Track::factory()->create(['label_id' => $label->id, 'isrc' => 'ZZTST2600001']);

    $import = importRightz($label, rightzLine('1.50', 'Example Radio', allocation: '[2026-01-01,2026-07-01)'));

    $transaction = Transaction::query()->sole();

    expect($import->errors_count)->toBe(0)
        ->and($transaction->track_id)->toBe($track->id)
        ->and($transaction->total)->toBe('1.500000000000000')
        ->and($transaction->timestamp->toDateString())->toBe('2026-06-30')
        ->and($transaction->paid_timestamp->toDateString())->toBe('2026-11-30')
        ->and($transaction->only(['category', 'format', 'provider', 'retailer', 'usage_type', 'region', 'currency']))->toBe([
            'category' => 'Rights', 'format' => 'Neighboring Rights', 'provider' => 'Example Society',
            'retailer' => 'Example Radio', 'usage_type' => 'Radioprogramm', 'region' => 'DE', 'currency' => 'EUR',
        ]);
});

test('a broadcaster named in a statement is kept as an airplay of the track', function () {
    $label = currentLabel(['currency' => 'EUR']);
    $track = Track::factory()->create(['label_id' => $label->id, 'isrc' => 'ZZTST2600001']);

    $import = importRightz($label, rightzLine('1.50', 'Example Radio'), rightzLine('0.25', null));

    $airplay = Airplay::query()->sole();

    expect(Transaction::query()->count())->toBe(2)
        ->and($airplay->only(['label_id', 'track_id', 'data_import_id', 'track_name', 'artist_name', 'isrc', 'station', 'media_type', 'region', 'society', 'amount']))->toBe([
            'label_id' => $label->id, 'track_id' => $track->id, 'data_import_id' => $import->id, 'track_name' => 'Example Track',
            'artist_name' => 'Example Artist', 'isrc' => 'ZZTST2600001', 'station' => 'Example Radio', 'media_type' => 'Radioprogramm',
            'region' => 'DE', 'society' => 'Example Society', 'amount' => '1.500000000000000',
        ])
        ->and($airplay->origin)->toBe(AirplayOrigin::Statement)
        ->and($airplay->period_start->toDateString())->toBe('2025-01-01')
        ->and($airplay->period_end->toDateString())->toBe('2025-12-31');
});

test('a later payment for the same play adds to its amount instead of listing it twice', function () {
    $label = currentLabel(['currency' => 'EUR']);

    importRightz($label, rightzLine('1.50', 'Example Radio'));
    importRightz($label, rightzLine('1.50', 'Example Radio'));
    importRightz($label, rightzLine('0.50', 'Example Radio'), rightzLine('2.00', 'Sample FM'));

    expect(Airplay::query()->orderBy('station')->get()->map->only(['station', 'amount'])->all())->toBe([
        ['station' => 'Example Radio', 'amount' => '2.000000000000000'],
        ['station' => 'Sample FM', 'amount' => '2.000000000000000'],
    ]);
});

test('deleting an import removes its airplays but keeps the ones with links or notes', function () {
    $label = currentLabel(['currency' => 'EUR']);
    $import = importRightz($label, rightzLine('1.50', 'Example Radio'), rightzLine('2.00', 'Sample FM'), rightzLine('3.00', 'Test Radio'));
    Airplay::query()->where('station', 'Sample FM')->update(['notes' => 'Played in the morning show.']);
    Airplay::query()->where('station', 'Test Radio')->sole()->update(['links' => [['url' => 'https://example.com/playlist', 'title' => null, 'description' => null]]]);

    app(ImportRunner::class)->delete($import);

    expect(Airplay::query()->orderBy('station')->pluck('station')->all())->toBe(['Sample FM', 'Test Radio']);
});
