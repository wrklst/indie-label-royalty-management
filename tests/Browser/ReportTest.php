<?php

use App\Models\Label;
use App\Models\PlayCount;
use App\Models\Release;
use App\Models\Track;
use App\Models\Transaction;

test('the dashboard draws how income developed by platform and what a stream paid', function () {
    $label = Label::factory()->create(['name' => 'Example Records']);
    $release = Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release']);
    $track = Track::factory()->create(['label_id' => $label->id, 'release_id' => $release->id, 'name' => 'Example Track']);
    $shared = ['label_id' => $label->id, 'release_id' => $release->id, 'track_id' => $track->id];

    foreach (['2026-01-31' => 1, '2026-02-28' => 2, '2026-03-31' => 3] as $day => $factor) {
        Transaction::factory()->create([...$shared, 'retailer' => 'Spotify', 'format' => 'Subscription Audio Stream', 'quantity' => 1000 * $factor, 'total' => (string) (3 * $factor), 'timestamp' => $day]);
        Transaction::factory()->create([...$shared, 'retailer' => 'Apple/iTunes', 'format' => 'Subscription Audio Stream', 'quantity' => 200 * $factor, 'total' => (string) $factor, 'timestamp' => $day]);
        Transaction::factory()->create([...$shared, 'retailer' => 'Bandcamp', 'format' => 'Download Album', 'quantity' => $factor, 'total' => (string) (8 * $factor), 'timestamp' => $day, 'region' => 'DE']);
    }

    PlayCount::factory()->create(['label_id' => $label->id, 'track_id' => $track->id, 'platform' => 'spotify', 'plays' => 6600, 'period_start' => '2026-01-01', 'period_end' => '2026-03-31']);
    $this->actingAs(labelAdmin($label));

    $page = visit('/dashboard?from=2026-01-01&to=2026-12-31')
        ->assertSee('How the income developed')
        ->assertSee('Apple Music')
        ->assertSee('Counted by the platform')
        ->assertSee('6,600')
        ->assertSee('Releases that earned most')
        ->assertPresent('[data-development="total"] [data-month="2026-03"]')
        ->assertPresent('[data-platform="spotify"] svg');

    $page->press('Per stream')
        ->assertPresent('[data-development="rate"] svg path')
        ->assertNoJavaScriptErrors();
});

test('the play counts page draws how the plays developed by platform and what was played most', function () {
    $label = Label::factory()->create(['name' => 'Example Records']);
    $release = Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release']);
    $track = Track::factory()->create(['label_id' => $label->id, 'release_id' => $release->id, 'name' => 'Example Track']);

    foreach (['2026-01-15' => 1, '2026-02-15' => 3, '2026-03-15' => 2] as $day => $factor) {
        PlayCount::factory()->create(['label_id' => $label->id, 'track_id' => $track->id, 'platform' => 'spotify', 'plays' => 1000 * $factor, 'period_start' => $day, 'period_end' => $day]);
        PlayCount::factory()->create(['label_id' => $label->id, 'track_id' => $track->id, 'platform' => 'youtube', 'plays' => 400 * $factor, 'period_start' => $day, 'period_end' => $day]);
    }

    Transaction::factory()->create(['label_id' => $label->id, 'release_id' => $release->id, 'track_id' => $track->id, 'retailer' => 'Spotify', 'format' => 'Subscription Audio Stream', 'quantity' => 900, 'total' => '3', 'timestamp' => '2026-01-31']);
    $this->actingAs(labelAdmin($label));

    visit('/play-counts')
        ->assertSee('How the plays developed')
        ->assertSee('Releases by plays')
        ->assertSee('Tracks by plays')
        ->assertSee('8,400')
        ->assertPresent('[data-development="streams"] [data-month="2026-03"]')
        ->assertPresent('[data-play-report] [data-platform="youtube"] svg')
        ->assertNoJavaScriptErrors();
});
