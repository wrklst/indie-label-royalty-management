<?php

use App\Enums\ImportStatus;
use App\Models\Release;
use App\Models\Transaction;
use App\Services\Imports\ImportRunner;

test('a utf-16 bandcamp report is imported with the one percent fee deducted', function () {
    $label = currentLabel();

    $import = app(ImportRunner::class)->run(pendingImport($label, 'bandcamp', 'bandcamp_raw_sample.csv'), $label);

    expect($import->status)->toBe(ImportStatus::Imported)
        ->and($import->transactions_count)->toBe(2)
        ->and($import->metadata['skipped'])->toBe(1);

    $track = Transaction::query()->where('format', 'Download Track')->sole();

    expect($track->total)->toBe('1.128600000000000')
        ->and($track->timestamp->toDateString())->toBe('2023-08-03')
        ->and($track->paid_timestamp->toDateString())->toBe('2023-08-03')
        ->and($track->external_id)->toBe('577718015')
        ->and($track->region)->toBe('AT')
        ->and($track->track->isrc)->toBe('ZZTST2600001')
        ->and($track->release->upc)->toBe('4000000000017')
        ->and(Release::count())->toBe(1);
});

test('the fee deduction can be switched off per label', function () {
    $label = currentLabel(['importer_settings' => ['bandcamp' => ['disable_fee' => true]]]);

    app(ImportRunner::class)->run(pendingImport($label, 'bandcamp', 'bandcamp_raw_sample.csv'), $label);

    expect(Transaction::query()->where('format', 'Download Album')->sole()->total)->toBe('5.600000000000000');
});

test('buyer names, emails and addresses are never stored', function () {
    $label = currentLabel();

    app(ImportRunner::class)->run(pendingImport($label, 'bandcamp', 'bandcamp_raw_sample.csv'), $label);

    $stored = json_encode(Transaction::query()->pluck('raw_data'));

    expect($stored)->not->toContain('Jane Example')
        ->not->toContain('jane@example.com')
        ->not->toContain('Vienna')
        ->not->toContain('label@example.com');
});
