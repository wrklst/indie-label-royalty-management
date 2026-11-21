<?php

use App\Models\Label;
use App\Models\Payee;
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

test('the date fields always show the range the figures are computed for, also when it is typed backwards', function () {
    $this->travelTo('2026-06-15');
    $label = Label::factory()->create();
    Transaction::factory()->create(['label_id' => $label->id, 'timestamp' => '2026-05-10', 'paid_timestamp' => '2026-05-10', 'memo' => 'In range']);
    $this->actingAs(labelAdmin($label));

    visit('/transactions?from=2026-05-01&to=2026-05-31')
        ->assertSee('In range')
        ->assertValue('input[aria-label="From"]', '2026-05-01')
        ->fill('input[aria-label="To"]', '2026-04-01')
        ->waitForText('No transaction matches')
        ->assertQueryStringHas('to', '2026-04-01')
        ->assertValue('input[aria-label="From"]', '2026-05-01')
        ->assertValue('input[aria-label="To"]', '2026-04-01')
        ->assertNoJavaScriptErrors();

    visit('/dashboard')
        ->assertValue('input[aria-label="To"]', '2026-06-30')
        ->fill('input[aria-label="To"]', '2024-12-31')
        ->waitForText('No income in this range.')
        ->assertQueryStringHas('to', '2024-12-31')
        ->assertValue('input[aria-label="To"]', '2024-12-31')
        ->assertValue('input[aria-label="From"]', '2025-07-01')
        ->assertNoJavaScriptErrors();
});

test('switching the type of a saved cost shows the amount as it will be stored', function () {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $cost = Transaction::factory()->create(['label_id' => $label->id, 'type' => 'expense', 'total' => '-250', 'converted_total' => '-250', 'currency' => 'EUR', 'recoupable' => true]);
    $this->actingAs(labelAdmin($label));

    visit("/transactions/{$cost->id}/edit")
        ->assertValue('#total', '250')
        ->select('#type', 'transfer')
        ->assertValue('#total', '-250')
        ->select('#type', 'expense')
        ->assertValue('#total', '250')
        ->select('#type', 'income')
        ->assertValue('#total', '250')
        ->assertSee('Paid by')
        ->assertNoJavaScriptErrors();
});
