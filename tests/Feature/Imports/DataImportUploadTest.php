<?php

use App\Models\DataImport;
use App\Models\Label;
use App\Models\Transaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function statementUpload(string $fixture): UploadedFile
{
    return UploadedFile::fake()->createWithContent($fixture, file_get_contents(base_path("tests/Fixtures/{$fixture}")));
}

test('an uploaded zebralution statement is imported into the current label', function () {
    Storage::fake('local');
    $label = Label::factory()->create();
    $admin = labelAdmin($label);

    $this->actingAs($admin)->post(route('data-imports.store'), [
        'importer' => 'zebralution',
        'file' => statementUpload('zebralution_2026q3_sample.csv'),
        'default_paid_timestamp' => '2026-10-15',
    ])->assertRedirect();

    $this->assertDatabaseHas('data_imports', [
        'label_id' => $label->id,
        'user_id' => $admin->id,
        'status' => 'imported',
        'transactions_count' => 67,
        'name' => 'zebralution_2026q3_sample',
    ]);

    expect(Transaction::query()->withoutGlobalScopes()->where('label_id', $label->id)->count())->toBe(67);
});

test('a statement without payment dates cannot be imported without a paid date', function () {
    Storage::fake('local');

    $this->actingAs(labelAdmin(Label::factory()->create()))->post(route('data-imports.store'), [
        'importer' => 'zebralution',
        'file' => statementUpload('zebralution_2026q3_sample.csv'),
    ])->assertSessionHasErrors('default_paid_timestamp');

    $this->assertDatabaseCount('data_imports', 0);
});

test('a bandcamp report needs no paid date', function () {
    Storage::fake('local');

    $this->actingAs(labelAdmin(Label::factory()->create()))->post(route('data-imports.store'), [
        'importer' => 'bandcamp',
        'file' => statementUpload('bandcamp_raw_sample.csv'),
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('data_imports', ['status' => 'imported', 'transactions_count' => 2]);
});

test('deleting an import over http removes its transactions', function () {
    Storage::fake('local');
    $label = Label::factory()->create();
    $admin = labelAdmin($label);
    $this->actingAs($admin)->post(route('data-imports.store'), ['importer' => 'bandcamp', 'file' => statementUpload('bandcamp_raw_sample.csv')]);
    $importId = DataImport::query()->withoutGlobalScopes()->sole()->id;

    $this->actingAs($admin)->delete(route('data-imports.destroy', $importId))->assertRedirect(route('data-imports.index'));

    $this->assertDatabaseCount('transactions', 0);
    $this->assertDatabaseHas('data_imports', ['id' => $importId, 'status' => 'deleted']);
});
