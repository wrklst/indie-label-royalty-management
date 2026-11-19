<?php

use App\Enums\AirplayOrigin;
use App\Models\Airplay;
use App\Models\AirplayFinding;
use App\Models\Artist;
use App\Models\Label;
use App\Models\Release;
use App\Models\Track;
use App\Models\Transaction;
use Inertia\Testing\AssertableInertia as Assert;

test('a track page shows where the track belongs, what it earned and where it was played', function () {
    $label = Label::factory()->create();
    $artist = Artist::factory()->create(['label_id' => $label->id, 'name' => 'Example Artist']);
    $release = Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release']);
    $track = Track::factory()->create(['label_id' => $label->id, 'release_id' => $release->id, 'artist_id' => $artist->id, 'name' => 'Example Track']);
    Transaction::factory()->count(2)->create([
        'label_id' => $label->id, 'release_id' => $release->id, 'track_id' => $track->id, 'type' => 'income', 'total' => '2.50', 'converted_total' => '2.50', 'retailer' => 'Example Shop',
    ]);
    Transaction::factory()->create(['label_id' => $label->id, 'release_id' => $release->id, 'track_id' => $track->id, 'type' => 'expense', 'total' => '-9', 'converted_total' => '-9']);
    $airplay = Airplay::factory()->create([
        'label_id' => $label->id, 'track_id' => $track->id, 'station' => 'Example Radio', 'origin' => AirplayOrigin::Statement, 'amount' => '1.50',
        'period_start' => '2025-01-01', 'period_end' => '2025-12-31',
    ]);
    Airplay::factory()->create(['label_id' => $label->id]);
    AirplayFinding::factory()->create(['label_id' => $label->id, 'track_id' => $track->id]);
    AirplayFinding::factory()->create(['label_id' => $label->id, 'track_id' => $track->id, 'status' => AirplayFinding::DISMISSED]);

    $this->actingAs(labelAdmin($label))
        ->get(route('tracks.show', $track->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tracks/show')
            ->where('track.name', 'Example Track')
            ->where('track.artists', [['id' => $artist->id, 'name' => 'Example Artist']])
            ->where('track.release.id', $release->id)
            ->where('income.transactions', 2)
            ->where('income.total', fn (string $total): bool => (float) $total === 5.0)
            ->where('income.byRetailer.0.label', 'Example Shop')
            ->has('airplays', 1)
            ->where('airplays.0.id', $airplay->id)
            ->where('airplays.0.origin', 'statement')
            ->where('airplays.0.period_end', '2025-12-31')
            ->where('openFindings', 1));
});

test('the track of another label has no page', function () {
    $track = Track::factory()->create();

    $this->actingAs(labelAdmin(Label::factory()->create()))->get(route('tracks.show', $track->id))->assertNotFound();
});

test('an artist page lists the releases and tracks credited to the artist', function () {
    $label = Label::factory()->create();
    $artist = Artist::factory()->create(['label_id' => $label->id, 'name' => 'Example Artist']);
    $release = Release::factory()->create(['label_id' => $label->id, 'artist_id' => $artist->id, 'name' => 'Example Release']);
    $track = Track::factory()->create(['label_id' => $label->id, 'release_id' => $release->id, 'artist_id' => $artist->id, 'name' => 'Example Track']);
    Airplay::factory()->create(['label_id' => $label->id, 'track_id' => $track->id]);
    Track::factory()->create(['label_id' => $label->id]);
    Release::factory()->create(['label_id' => $label->id]);

    $this->actingAs(labelAdmin($label))
        ->get(route('artists.show', $artist->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('artists/show')
            ->where('artist', ['id' => $artist->id, 'name' => 'Example Artist'])
            ->has('releases', 1)
            ->where('releases.0.id', $release->id)
            ->has('tracks', 1)
            ->where('tracks.0.id', $track->id)
            ->where('tracks.0.release', ['id' => $release->id, 'name' => 'Example Release'])
            ->where('tracks.0.airplays_count', 1));
});

test('the artist of another label has no page', function () {
    $artist = Artist::factory()->create();

    $this->actingAs(labelAdmin(Label::factory()->create()))->get(route('artists.show', $artist->id))->assertNotFound();
});

test('the airplay page and the transaction list can be limited to one track', function () {
    $label = Label::factory()->create();
    $track = Track::factory()->create(['label_id' => $label->id, 'name' => 'Example Track']);
    $airplay = Airplay::factory()->create(['label_id' => $label->id, 'track_id' => $track->id]);
    Airplay::factory()->create(['label_id' => $label->id]);
    $transaction = Transaction::factory()->create(['label_id' => $label->id, 'track_id' => $track->id]);
    Transaction::factory()->create(['label_id' => $label->id]);
    $admin = labelAdmin($label);

    $this->actingAs($admin)->get(route('airplays.index', ['track' => $track->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('airplays', 1)
            ->where('airplays.0.id', $airplay->id)
            ->where('onlyTrack', ['id' => $track->id, 'name' => 'Example Track']));

    $this->actingAs($admin)->get(route('transactions.index', ['track' => $track->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.id', $transaction->id));
});

test('a track on several releases shows every release and combines airplay and income of the recording', function () {
    $label = Label::factory()->create();
    $first = Track::factory()->create(['label_id' => $label->id, 'release_id' => Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release']), 'isrc' => 'ZZTST2600001']);
    $second = Track::factory()->create(['label_id' => $label->id, 'release_id' => Release::factory()->create(['label_id' => $label->id, 'name' => 'Sample Compilation']), 'isrc' => 'ZZTST2600001']);
    Track::factory()->create(['isrc' => 'ZZTST2600001']);
    Transaction::factory()->create(['label_id' => $label->id, 'track_id' => $first->id, 'type' => 'income', 'total' => '2', 'converted_total' => '2']);
    Transaction::factory()->create(['label_id' => $label->id, 'track_id' => $second->id, 'type' => 'income', 'total' => '3', 'converted_total' => '3']);
    $airplay = Airplay::factory()->create(['label_id' => $label->id, 'track_id' => $second->id]);
    $admin = labelAdmin($label);

    $this->actingAs($admin)
        ->get(route('tracks.show', $first->id))
        ->assertInertia(fn (Assert $page) => $page
            ->has('releases', 2)
            ->where('releases.0.name', 'Example Release')
            ->where('releases.1.name', 'Sample Compilation')
            ->where('releases.1.track_id', $second->id)
            ->where('income.transactions', 2)
            ->where('income.total', fn (string $total): bool => (float) $total === 5.0)
            ->where('airplays.0.id', $airplay->id));

    $this->actingAs($admin)->get(route('airplays.index', ['track' => $first->id]))
        ->assertInertia(fn (Assert $page) => $page->has('airplays', 1)->where('airplays.0.id', $airplay->id));
});
