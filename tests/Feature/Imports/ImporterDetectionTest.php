<?php

use App\Models\DataImport;
use App\Models\Label;
use App\Models\Transaction;
use App\Services\Imports\ImporterRegistry;
use App\Services\Imports\ImportException;
use App\Services\Imports\ImportRunner;
use App\Services\Imports\Readers\ReaderFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

function resolveImporter(string $choice, string $path): string
{
    return app(ImporterRegistry::class)->resolve($choice, app(ReaderFactory::class)->open($path))->key();
}

test('the layout of a file is detected from its columns', function (string $fixture, string $choice, string $expected) {
    expect(resolveImporter($choice, base_path("tests/Fixtures/{$fixture}")))->toBe($expected);
})->with([
    'current zebralution, any source' => ['zebralution_2026q3_sample.csv', 'auto', 'zebralution'],
    'legacy zebralution, any source' => ['zebralution_legacy_sample.csv', 'auto', 'zebralution-legacy'],
    'legacy zebralution, source chosen' => ['zebralution_legacy_sample.csv', 'zebralution', 'zebralution-legacy'],
    'bandcamp, any source' => ['bandcamp_raw_sample.csv', 'auto', 'bandcamp'],
]);

test('a generic spreadsheet falls back to the template and never to audio rights', function () {
    expect(resolveImporter('auto', xlsxFile([['Date', 'Amount'], [['date' => '2026-03-31'], 10]])))->toBe('template');
});

test('a file of another source is refused with the columns that were expected', function () {
    resolveImporter('zebralution', base_path('tests/Fixtures/bandcamp_raw_sample.csv'));
})->throws(ImportException::class, 'Rev.less Publ.EUR');

test('a file that fits no importer is refused', function () {
    resolveImporter('auto', xlsxFile([['Foo', 'Bar'], ['a', 'b']]));
})->throws(ImportException::class, 'not recognised');

test('a legacy zebralution statement is imported', function () {
    $label = currentLabel();

    $import = app(ImportRunner::class)
        ->run(pendingImport($label, 'zebralution-legacy', 'zebralution_legacy_sample.csv', ['default_paid_timestamp' => '2023-12-15']), $label);

    $first = Transaction::query()->orderBy('id')->first();

    expect($import->transactions_count)->toBe(3)
        ->and($first->total)->toBe('0.006400000000000')
        ->and($first->timestamp->toDateString())->toBe('2023-09-30')
        ->and($first->retailer)->toBe('AppleMusic')
        ->and($first->format)->toBe('Subscription Audio Stream')
        ->and($first->track->isrc)->toBe('ZZTST2300001');
});

test('an uploaded excel file with a title block is detected and imported', function () {
    Storage::fake('local');
    $label = Label::factory()->create();
    $path = xlsxFile([
        ['Statement for Example Label'],
        [],
        ['Date', 'Paid date', 'Type', 'Amount', 'Release', 'ISRC', 'Memo'],
        [['date' => '2026-03-31'], ['date' => '2026-04-15'], 'income', 125.5, 'Example Release', 'ZZ-TST-26-00001', 'Sync fee'],
        [['date' => '2026-03-31'], ['date' => '2026-04-15'], 'expense', -40, 'Example Release', null, 'Mastering'],
        [['date' => '2026-03-31'], ['date' => '2026-04-15'], 'refund', 1, 'Example Release', null, null],
    ]);

    $this->actingAs(labelAdmin($label))->post(route('data-imports.store'), [
        'importer' => 'auto',
        'file' => new UploadedFile($path, 'statement.xlsx', test: true),
    ])->assertSessionHasNoErrors();

    $import = DataImport::query()->withoutGlobalScopes()->sole();
    $income = Transaction::query()->withoutGlobalScopes()->where('type', 'income')->sole();

    expect($import->importer)->toBe('template')
        ->and($import->transactions_count)->toBe(2)
        ->and($import->errors_count)->toBe(1)
        ->and($import->errors[0])->toBe(['line' => 6, 'message' => '"transaction type" must be income, expense or transfer.'])
        ->and($income->total)->toBe('125.500000000000000')
        ->and($income->timestamp->toDateString())->toBe('2026-03-31')
        ->and($income->paid_timestamp->toDateString())->toBe('2026-04-15')
        ->and($income->memo)->toBe('Sync fee');
});

test('an unrecognised upload is rejected before anything is stored', function () {
    Storage::fake('local');

    $this->actingAs(labelAdmin(Label::factory()->create()))->post(route('data-imports.store'), [
        'importer' => 'bandcamp',
        'file' => new UploadedFile(base_path('tests/Fixtures/zebralution_2026q3_sample.csv'), 'statement.csv', test: true),
    ])->assertSessionHasErrors('file');

    $this->assertDatabaseCount('data_imports', 0);
});

test('a generated importer is discovered and works without registration', function () {
    $path = app_path('Services/Imports/Importers/GeneratedExampleImporter.php');

    try {
        $this->artisan('make:importer', ['name' => 'GeneratedExampleImporter', '--source' => 'zebralution', '--layout' => '2030 onwards'])->assertSuccessful();

        $importer = new ImporterRegistry()->find('zebralution-2030-onwards');

        expect($importer?->source())->toBe('zebralution')
            ->and($importer->name())->toBe('Zebralution (2030 onwards)');
    } finally {
        File::delete($path);
    }
});
