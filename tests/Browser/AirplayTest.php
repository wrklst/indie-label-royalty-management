<?php

use App\Enums\AirplayOrigin;
use App\Models\Airplay;
use App\Models\AirplayFinding;
use App\Models\Label;
use App\Models\Track;

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
        ->assertNoJavaScriptErrors();

    expect(Airplay::query()->withoutGlobalScope('label')->sole()->origin)->toBe(AirplayOrigin::Search);
});

test('a link is added to an airplay in its links dialog', function () {
    $label = Label::factory()->create();
    $airplay = Airplay::factory()->create(['label_id' => $label->id, 'track_name' => 'Example Track', 'station' => 'Example Radio']);
    $this->actingAs(labelAdmin($label));

    visit('/airplays')
        ->click('[aria-label="Links for Example Track on Example Radio"]')
        ->fill('url', 'https://example.com/playlist')
        ->fill('title', 'Playlist')
        ->press('Add link')
        ->assertSeeIn('[role="dialog"]', 'Added by hand')
        ->assertNoJavaScriptErrors();

    expect($airplay->refresh()->links[0]['url'])->toBe('https://example.com/playlist');
});
