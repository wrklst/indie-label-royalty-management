<?php

use App\Models\Label;
use App\Models\Release;
use App\Models\Track;
use App\Models\Transaction;

test('a list is filtered from its filter bar, keeps the order and clears its filters in one click', function () {
    $label = Label::factory()->create();
    Release::factory()->create(['label_id' => $label->id, 'name' => 'Alpha Release', 'catalog_number' => 'EX001']);
    Release::factory()->create(['label_id' => $label->id, 'name' => 'Bravo Release', 'catalog_number' => 'EX002']);
    $this->actingAs(labelAdmin($label));

    visit('/releases?sort=name&direction=desc')
        ->assertDontSee('Clear filters')
        ->type('input[type="search"]', 'Alpha')
        ->waitForText('Clear filters')
        ->assertQueryStringHas('search', 'Alpha')
        ->assertQueryStringHas('sort', 'name')
        ->assertSee('Alpha Release')
        ->assertDontSee('Bravo Release')
        ->click('Clear filters')
        ->waitForText('Bravo Release')
        ->assertQueryStringMissing('search')
        ->assertQueryStringHas('sort', 'name')
        ->assertDontSee('Clear filters')
        ->assertNoJavaScriptErrors();
});

test('a link that opens the transaction list for a track shows the filter and can clear it', function () {
    $label = Label::factory()->create();
    $track = Track::factory()->create(['label_id' => $label->id, 'name' => 'Example Track']);
    Transaction::factory()->create(['label_id' => $label->id, 'track_id' => $track->id, 'memo' => 'On the track']);
    Transaction::factory()->create(['label_id' => $label->id, 'memo' => 'Elsewhere']);
    $this->actingAs(labelAdmin($label));

    visit("/transactions?track={$track->id}")
        ->assertSee('Clear filters')
        ->assertSee('On the track')
        ->assertDontSee('Elsewhere')
        ->click('Clear filters')
        ->waitForText('Elsewhere')
        ->assertQueryStringMissing('track')
        ->assertNoJavaScriptErrors();
});
