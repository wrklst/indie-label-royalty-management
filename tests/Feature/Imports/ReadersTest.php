<?php

use App\Services\Imports\ImportException;
use App\Services\Imports\Readers\CsvReader;
use App\Services\Imports\Readers\ReaderFactory;
use App\Services\Imports\Readers\XlsxReader;

function csvFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $contents);

    return $path;
}

test('an excel sheet is read as text, with dates in iso format and empty cells kept in place', function () {
    $reader = new XlsxReader(xlsxFile([
        ['Date', 'Track', 'Amount', 'Units'],
        [['date' => '2026-03-31'], 'Señor & Sons', 0.000271889626, 3],
        [['date' => '2026-04-01'], null, 12.5, null],
    ]));

    expect($reader->headers())->toBe(['Date', 'Track', 'Amount', 'Units'])
        ->and(iterator_to_array($reader->rows()))->toBe([
            2 => ['Date' => '2026-03-31', 'Track' => 'Señor & Sons', 'Amount' => '0.000271889626', 'Units' => '3'],
            3 => ['Date' => '2026-04-01', 'Track' => '', 'Amount' => '12.5', 'Units' => ''],
        ]);
});

test('title lines above the header row are skipped once the header is located', function () {
    $reader = new CsvReader(csvFile("Royalty statement Q3\nAccount;4711\n\nDate;Amount\n2026-03-31;1,50\n"));

    $found = $reader->locateHeader(fn (array $cells): bool => in_array('Amount', $cells, true));

    expect($found)->toBeTrue()
        ->and($reader->headers())->toBe(['Date', 'Amount'])
        ->and(iterator_to_array($reader->rows()))->toBe([5 => ['Date' => '2026-03-31', 'Amount' => '1,50']]);
});

test('the header stays on the first line when no line is accepted', function () {
    $reader = new CsvReader(csvFile("Date,Amount\n2026-03-31,1.50\n"));

    expect($reader->locateHeader(fn (array $cells): bool => in_array('ISRC', $cells, true)))->toBeFalse()
        ->and($reader->headers())->toBe(['Date', 'Amount']);
});

test('the file format is taken from the contents, not the name', function () {
    $factory = new ReaderFactory;

    expect($factory->open(xlsxFile([['Amount'], [1]])))->toBeInstanceOf(XlsxReader::class)
        ->and($factory->open(csvFile("Amount\n1\n")))->toBeInstanceOf(CsvReader::class);
});

test('old binary excel files are refused with advice', function () {
    new ReaderFactory()->open(csvFile("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1 binary"));
})->throws(ImportException::class, 'Save the file as .xlsx or CSV');
