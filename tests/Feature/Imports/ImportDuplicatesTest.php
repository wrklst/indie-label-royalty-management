<?php

use App\Enums\ImportStatus;
use App\Models\Contract;
use App\Models\DataImport;
use App\Models\Label;
use App\Models\Release;
use App\Models\Transaction;
use App\Services\Imports\ImportRunner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A booking someone entered by hand for the release.
 */
function manualBooking(Release $release, string $total, array $attributes = []): Transaction
{
    return Transaction::factory()->create([
        'label_id' => $release->label_id, 'release_id' => $release->id, 'data_import_id' => null, 'type' => 'income',
        'timestamp' => '2026-03-31', 'paid_timestamp' => '2026-03-31', 'total' => $total, 'converted_total' => $total,
        'currency' => 'EUR', 'format' => 'Performance Rights', 'importer_source' => 'manual', ...$attributes,
    ]);
}

/**
 * Upload template lines for the release "Example Release" as the given admin would.
 */
function pendingTemplate(Label $label, string $lines): DataImport
{
    Storage::disk('local')->put('imports/duplicates.csv', "paid date,total,release name,format,memo\n{$lines}\n");

    return DataImport::factory()->create([
        'label_id' => $label->id, 'importer' => 'template', 'status' => 'pending', 'file_path' => 'imports/duplicates.csv',
    ]);
}

test('an import whose lines add up to an existing booking of the release is held and nothing is saved', function () {
    $label = currentLabel(['currency' => 'EUR']);
    $release = Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release']);
    $booking = manualBooking($release, '11.39', ['memo' => 'Entered by hand']);

    $import = app(ImportRunner::class)->run(pendingTemplate($label, "2026-03-15,11.31912,Example Release,Neighboring Rights,a\n2026-03-20,0.06956,Example Release,Neighboring Rights,b"), $label);

    expect($import->status)->toBe(ImportStatus::Review)
        ->and(Transaction::query()->pluck('id')->all())->toBe([$booking->id])
        ->and($import->metadata['duplicates'])->toHaveCount(1)
        ->and(collect($import->metadata['duplicates'][0])->except('key')->all())->toBe([
            'kind' => 'sum',
            'release' => 'Example Release',
            'existing' => [['id' => $booking->id, 'paid' => '2026-03-31', 'total' => '11.39', 'memo' => 'Entered by hand', 'source' => 'manual']],
            'existing_total' => '11.39',
            'imported' => ['count' => 2, 'total' => '11.39', 'tracks' => []],
        ]);
});

test('an upload that is held for duplicates is a warning, not an error', function () {
    Storage::fake('local');
    $label = Label::factory()->create(['currency' => 'EUR']);
    $release = Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release']);
    manualBooking($release, '25.00');

    $this->actingAs(labelAdmin($label))->post(route('data-imports.store'), [
        'importer' => 'template',
        'file' => UploadedFile::fake()->createWithContent('example.csv', "paid date,total,release name,format,memo\n2026-03-31,25,Example Release,Neighboring Rights,a\n2026-03-31,40,Example Release,Neighboring Rights,b\n"),
    ])->assertInertiaFlash('toast.type', 'warning');
});

test('a single line with the paid date and amount of an existing booking is held as a possible duplicate', function () {
    $label = currentLabel(['currency' => 'EUR']);
    $release = Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release']);
    manualBooking($release, '25.00');

    $import = app(ImportRunner::class)->run(pendingTemplate($label, "2026-03-31,25,Example Release,Neighboring Rights,a\n2026-03-31,40,Example Release,Neighboring Rights,b"), $label);

    expect($import->status)->toBe(ImportStatus::Review)
        ->and($import->metadata['duplicates'])->toHaveCount(1)
        ->and($import->metadata['duplicates'][0]['kind'])->toBe('line')
        ->and($import->metadata['duplicates'][0]['imported'])->toBe(['count' => 1, 'total' => '25.00', 'tracks' => []]);
});

test('an import is not held', function (array $booking, string $lines) {
    $label = currentLabel(['currency' => 'EUR']);
    $release = Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release']);
    manualBooking($release, '25.00', $booking);

    $import = app(ImportRunner::class)->run(pendingTemplate($label, $lines), $label);

    expect($import->status)->toBe(ImportStatus::Imported)
        ->and(Transaction::query()->count())->toBe(2);
})->with([
    'for another amount' => [[], '2026-03-31,26,Example Release,Neighboring Rights,a'],
    'for the same amount in another month' => [[], '2026-04-30,25,Example Release,Neighboring Rights,a'],
    'for the same amount in another kind of format' => [[], '2026-03-31,25,Example Release,Download Album,a'],
    'for a booking of the same importer' => [['importer_source' => 'template'], '2026-03-31,25,Example Release,Neighboring Rights,a'],
    'for an expense of the same amount' => [['type' => 'expense'], '2026-03-31,25,Example Release,Neighboring Rights,a'],
]);

test('replacing keeps the imported lines, removes the booking and carries over what it said', function () {
    $label = Label::factory()->create(['currency' => 'EUR']);
    $release = Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release']);
    $contract = Contract::factory()->create(['label_id' => $label->id]);
    $admin = labelAdmin($label);
    $this->actingAs($admin);
    $booking = manualBooking($release, '11.39', ['published' => false, 'contract_id' => $contract->id]);
    $import = app(ImportRunner::class)->run(pendingTemplate($label, "2026-03-15,11.31912,Example Release,Neighboring Rights,a\n2026-03-20,0.06956,Example Release,Neighboring Rights,b"), $label);
    $key = $import->metadata['duplicates'][0]['key'];

    $this->post(route('data-imports.resolve', $import->id), ['decisions' => [$key => 'replace']])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('data-imports.show', $import->id));

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Imported)
        ->and($import->transactions_count)->toBe(2)
        ->and($import->metadata['resolved_duplicates'][0]['decision'])->toBe('replace')
        ->and($import->metadata['resolved_duplicates'][0]['existing'][0]['id'])->toBe($booking->id)
        ->and(Transaction::query()->withoutGlobalScope('label')->find($booking->id))->toBeNull()
        ->and(Transaction::query()->withoutGlobalScope('label')->orderBy('id')->get()->map->only(['data_import_id', 'published', 'contract_id'])->all())->toBe([
            ['data_import_id' => $import->id, 'published' => false, 'contract_id' => $contract->id],
            ['data_import_id' => $import->id, 'published' => false, 'contract_id' => $contract->id],
        ]);
});

test('skipping leaves the duplicate lines out and imports the rest', function () {
    $label = Label::factory()->create(['currency' => 'EUR']);
    $release = Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release']);
    $this->actingAs(labelAdmin($label));
    $booking = manualBooking($release, '25.00');
    $import = app(ImportRunner::class)->run(pendingTemplate($label, "2026-03-31,25,Example Release,Neighboring Rights,a\n2026-03-31,40,Example Release,Neighboring Rights,b"), $label);

    $this->post(route('data-imports.resolve', $import->id), ['decisions' => [$import->metadata['duplicates'][0]['key'] => 'skip']])->assertSessionHasNoErrors();

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Imported)
        ->and($import->transactions_count)->toBe(1)
        ->and($import->total)->toBe('40.000000000000000')
        ->and(Transaction::query()->withoutGlobalScope('label')->orderBy('id')->pluck('total', 'id')->map(fn (string $total): float => (float) $total)->values()->all())->toBe([25.0, 40.0])
        ->and(Transaction::query()->withoutGlobalScope('label')->find($booking->id))->not->toBeNull();
});

test('keeping both imports every line next to the booking and does not ask again', function () {
    $label = Label::factory()->create(['currency' => 'EUR']);
    $release = Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release']);
    $this->actingAs(labelAdmin($label));
    manualBooking($release, '25.00');
    $import = app(ImportRunner::class)->run(pendingTemplate($label, '2026-03-31,25,Example Release,Neighboring Rights,a'), $label);

    $this->post(route('data-imports.resolve', $import->id), ['decisions' => [$import->metadata['duplicates'][0]['key'] => 'both']])->assertSessionHasNoErrors();

    expect($import->refresh()->status)->toBe(ImportStatus::Imported)
        ->and(Transaction::query()->withoutGlobalScope('label')->count())->toBe(2);
});

test('a held import is not resolved', function (Closure $input, int|string $outcome) {
    $label = Label::factory()->create(['currency' => 'EUR']);
    $release = Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release']);
    $admin = labelAdmin($label);
    $this->actingAs($admin);
    manualBooking($release, '25.00');
    $import = app(ImportRunner::class)->run(pendingTemplate($label, '2026-03-31,25,Example Release,Neighboring Rights,a'), $label);

    $response = $this->post(route('data-imports.resolve', $import->id), $input($import->metadata['duplicates'][0]['key']));

    is_int($outcome) ? $response->assertStatus($outcome) : $response->assertSessionHasErrors($outcome);

    expect($import->refresh()->status)->toBe(ImportStatus::Review)
        ->and(Transaction::query()->withoutGlobalScope('label')->count())->toBe(1);
})->with([
    'without a decision' => [fn (string $key) => [], 'decisions'],
    'with an unknown decision' => [fn (string $key) => ['decisions' => [$key => 'merge']], 'decisions.*'],
    'for a duplicate it does not have' => [fn (string $key) => ['decisions' => ['unknown' => 'replace']], 'decisions'],
]);

test('the import of another label cannot be resolved', function () {
    $import = DataImport::factory()->create(['status' => 'review', 'metadata' => ['duplicates' => [['key' => 'example']]]]);

    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->post(route('data-imports.resolve', $import->id), ['decisions' => ['example' => 'replace']])
        ->assertNotFound();
});

test('the import command reports held duplicates instead of importing', function () {
    $label = Label::factory()->create(['currency' => 'EUR']);
    $release = Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release']);
    manualBooking($release, '25.00');
    $path = sys_get_temp_dir().'/'.uniqid('duplicates-', true).'.csv';
    file_put_contents($path, "paid date,total,release name,format\n2026-03-31,25,Example Release,Neighboring Rights\n");

    $this->artisan('royalties:import', ['file' => $path, '--label' => $label->id])
        ->expectsOutputToContain('1 possible duplicate of an existing transaction')
        ->assertFailed();

    unlink($path);

    expect(Transaction::query()->withoutGlobalScope('label')->count())->toBe(1);
});
