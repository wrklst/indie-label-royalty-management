<?php

use App\Services\Imports\Identifiers;
use App\Services\Imports\Row;
use App\Services\Imports\RowException;
use App\Support\Decimal;

test('an amount keeps its sign and loses its currency', function (string $written, string $amount) {
    expect(Decimal::parse($written))->toBe($amount);
})->with([
    'accounting brackets' => ['(12.34)', '-12.340000000000000'],
    'a typeset minus sign' => ['−12.34', '-12.340000000000000'],
    'a dash with a decimal comma' => ['–12,34', '-12.340000000000000'],
    'a currency code in front' => ['EUR 12,34', '12.340000000000000'],
    'a currency code behind' => ['12.34 EUR', '12.340000000000000'],
    'an exponent' => ['1.5e3', '1500.000000000000000'],
]);

test('a upc a spreadsheet turned into an exponent is not a upc', function () {
    expect(Identifiers::upc('4,01235E+12'))->toBeNull()
        ->and(Identifiers::upc('0 12345-67890 5'))->toBe('012345678905');
});

test('a date is read as a date or rejected', function (string $written, ?string $date) {
    $row = new Row(2, ['Date' => $written], [Row::normalize('Date') => 'Date']);

    if ($date === null) {
        expect(fn () => $row->date('Date'))->toThrow(RowException::class);
    } else {
        expect($row->date('Date'))->toBe($date);
    }
})->with([
    'iso' => ['2026-03-31', '2026-03-31'],
    'german with a full year' => ['31.03.2026', '2026-03-31'],
    'german with a short year' => ['03.04.26', '2026-04-03'],
    'a year alone' => ['2025', null],
    'a month and a year' => ['12.2025', null],
    'a word' => ['tomorrow', null],
    'day and month in an order nobody can tell' => ['03/04/2026', null],
]);
