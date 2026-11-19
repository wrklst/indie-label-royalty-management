<?php

use App\Enums\AirplayOrigin;
use App\Models\Airplay;
use App\Models\AirplayFinding;
use App\Models\DataImport;
use App\Models\Label;
use App\Models\Release;
use App\Models\Track;
use App\Models\Transaction;
use App\Services\Imports\ImportRunner;
use Illuminate\Support\Facades\Storage;

test('an airplay is added by hand and shows where it is known from', function () {
    $label = Label::factory()->create();
    Airplay::factory()->create(['label_id' => $label->id, 'track_name' => 'Example Track', 'station' => 'Example Radio', 'origin' => AirplayOrigin::Statement]);
    $this->actingAs(labelAdmin($label));

    visit('/airplays')
        ->assertSeeIn('[data-slot="table"]', 'Neighbouring rights statement')
        ->click('Add airplay')
        ->fill('track_name', 'Sample Track')
        ->fill('station', 'Sample FM')
        ->press('[role="dialog"] button:has-text("Add airplay")')
        ->assertSee('Airplay added.')
        ->assertSeeIn('[data-slot="table"]', 'Sample FM')
        ->assertSeeIn('[data-slot="table"]', 'Entered by hand')
        ->assertMissing('[role="dialog"]')
        ->assertNoJavaScriptErrors();
});

test('a finding is kept as a new airplay and leaves the list of findings', function () {
    $label = Label::factory()->create();
    $track = Track::factory()->create(['label_id' => $label->id, 'name' => 'Example Track']);
    AirplayFinding::factory()->create([
        'label_id' => $label->id, 'track_id' => $track->id, 'title' => 'Playlist of the morning show', 'domain' => 'radio.example.com', 'is_radio' => true,
    ]);
    $this->actingAs(labelAdmin($label));

    visit('/airplays')
        ->assertSee('Findings to review (1)')
        ->assertSee('Looks like radio')
        ->press('Keep')
        ->assertDontSee('Findings to review')
        ->assertSee('radio.example.com')
        ->assertSee('Web search')
        ->assertSee('Not researched yet')
        ->assertNoJavaScriptErrors();

    expect(Airplay::query()->withoutGlobalScope('label')->sole()->origin)->toBe(AirplayOrigin::Search);
});

test('a link is added to an airplay in its links dialog', function () {
    $label = Label::factory()->create();
    $airplay = Airplay::factory()->create(['label_id' => $label->id, 'track_name' => 'Example Track', 'station' => 'Example Radio']);
    $this->actingAs(labelAdmin($label));

    visit('/airplays')
        ->click('[aria-label="Links for Example Track on Example Radio"]')
        ->fill('url', 'https://example.com/'.str_repeat('a-very-long-playlist-address/', 12))
        ->fill('title', 'Playlist')
        ->press('Add link')
        ->assertSeeIn('[role="dialog"]', 'Added by hand')
        ->assertScript('document.querySelector(\'[role="dialog"]\').scrollWidth <= document.querySelector(\'[role="dialog"]\').clientWidth', true)
        ->assertNoJavaScriptErrors();

    expect($airplay->refresh()->links[0]['url'])->toStartWith('https://example.com/a-very-long');
});

test('an import held for a possible duplicate is merged from its review screen', function () {
    $label = Label::factory()->create(['currency' => 'EUR']);
    $release = Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release']);
    $booking = Transaction::factory()->create([
        'label_id' => $label->id, 'release_id' => $release->id, 'data_import_id' => null, 'type' => 'income', 'timestamp' => '2026-03-31',
        'paid_timestamp' => '2026-03-31', 'total' => '25', 'converted_total' => '25', 'currency' => 'EUR', 'format' => 'Performance Rights',
        'importer_source' => 'manual', 'memo' => 'Entered by hand',
    ]);
    Storage::disk('local')->put('imports/duplicates.csv', "paid date,total,release name,format\n2026-03-31,25,Example Release,Neighboring Rights\n");
    $import = app(ImportRunner::class)->run(DataImport::factory()->create([
        'label_id' => $label->id, 'importer' => 'template', 'status' => 'pending', 'file_path' => 'imports/duplicates.csv',
    ]), $label);
    $this->actingAs(labelAdmin($label));

    visit("/data-imports/{$import->id}")
        ->assertSee('Possible duplicates (1)')
        ->assertSee('Entered by hand')
        ->press('Import with these decisions')
        ->assertSee('1 transactions imported.')
        ->assertSee('Duplicates decided at import')
        ->assertNoJavaScriptErrors();

    expect(Transaction::query()->withoutGlobalScope('label')->find($booking->id))->toBeNull()
        ->and(Transaction::query()->withoutGlobalScope('label')->sole()->data_import_id)->toBe($import->id);
});
