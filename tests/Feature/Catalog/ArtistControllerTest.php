<?php

use App\Models\Artist;
use App\Models\Label;
use App\Models\Release;
use App\Models\Track;
use Inertia\Testing\AssertableInertia as Assert;

test('the artist list shows each artist with its alias target and usage counts', function () {
    $label = Label::factory()->create();
    $main = Artist::factory()->create(['label_id' => $label->id, 'name' => 'Example Artist']);
    Artist::factory()->create(['label_id' => $label->id, 'name' => 'Example Alias', 'alias_of_id' => $main->id]);
    $release = Release::factory()->create(['label_id' => $label->id, 'artist_id' => $main->id]);
    Track::factory()->count(2)->create(['label_id' => $label->id, 'release_id' => $release->id, 'artist_id' => $main->id]);

    $this->actingAs(labelAdmin($label))
        ->get(route('artists.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('artists/index')
            ->has('artists.data', 2)
            ->where('artists.data.0.name', 'Example Alias')
            ->where('artists.data.0.alias_of', 'Example Artist')
            ->where('artists.data.1.name', 'Example Artist')
            ->where('artists.data.1.alias_of', null)
            ->where('artists.data.1.releases_count', 1)
            ->where('artists.data.1.tracks_count', 2)
            ->where('artists.data.1.aliases_count', 1)
            ->has('artistOptions', 2));
});

test('searching the artist list matches part of the name', function () {
    $label = Label::factory()->create();
    Artist::factory()->create(['label_id' => $label->id, 'name' => 'Example Artist']);
    Artist::factory()->create(['label_id' => $label->id, 'name' => 'Sample Ensemble']);

    $this->actingAs(labelAdmin($label))
        ->get(route('artists.index', ['search' => ' ensem ']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('artists.data', 1)
            ->where('artists.data.0.name', 'Sample Ensemble')
            ->where('filters.search', 'ensem'));
});

test('an artist is created in the current label', function () {
    $label = Label::factory()->create();
    $other = Label::factory()->create();
    $main = Artist::factory()->create(['label_id' => $label->id]);

    $this->actingAs(labelAdmin($label))
        ->from(route('artists.index'))
        ->post(route('artists.store'), [
            'label_id' => $other->id,
            'name' => 'Example Alias',
            'alias_of_id' => $main->id,
            'spotify_id' => 'example-spotify-id',
            'discogs_search_name' => 'Example Alias (2)',
            'notes' => 'Side project.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('artists.index'));

    $this->assertDatabaseHas('artists', [
        'label_id' => $label->id,
        'name' => 'Example Alias',
        'alias_of_id' => $main->id,
        'spotify_id' => 'example-spotify-id',
        'discogs_search_name' => 'Example Alias (2)',
        'notes' => 'Side project.',
    ]);
});

test('an artist cannot be created without a name', function () {
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->post(route('artists.store'), ['name' => ''])
        ->assertSessionHasErrors(['name' => 'The name field is required.']);

    $this->assertDatabaseCount('artists', 0);
});

test('an empty alias selection is stored as no alias', function () {
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->post(route('artists.store'), ['name' => 'Example Artist', 'alias_of_id' => ''])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('artists', ['name' => 'Example Artist', 'alias_of_id' => null]);
});

test('an artist of another label cannot be chosen as alias target', function () {
    $label = Label::factory()->create();
    $foreign = Artist::factory()->create(['label_id' => Label::factory()]);

    $this->actingAs(labelAdmin($label))
        ->post(route('artists.store'), ['name' => 'Example Alias', 'alias_of_id' => $foreign->id])
        ->assertSessionHasErrors('alias_of_id');

    $this->assertDatabaseMissing('artists', ['name' => 'Example Alias']);
});

test('an artist is updated', function () {
    $label = Label::factory()->create();
    $main = Artist::factory()->create(['label_id' => $label->id]);
    $artist = Artist::factory()->create(['label_id' => $label->id, 'name' => 'Example Artist']);

    $this->actingAs(labelAdmin($label))
        ->from(route('artists.index'))
        ->put(route('artists.update', $artist->id), ['name' => 'Renamed Artist', 'alias_of_id' => $main->id, 'notes' => 'Now an alias.'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('artists.index'));

    $this->assertDatabaseHas('artists', ['id' => $artist->id, 'name' => 'Renamed Artist', 'alias_of_id' => $main->id, 'notes' => 'Now an alias.']);
});

test('an artist cannot be an alias of itself', function () {
    $label = Label::factory()->create();
    $artist = Artist::factory()->create(['label_id' => $label->id, 'name' => 'Example Artist']);

    $this->actingAs(labelAdmin($label))
        ->put(route('artists.update', $artist->id), ['name' => 'Example Artist', 'alias_of_id' => $artist->id])
        ->assertSessionHasErrors('alias_of_id');

    $this->assertDatabaseHas('artists', ['id' => $artist->id, 'alias_of_id' => null]);
});

test('deleting an artist keeps its releases and aliases without the artist', function () {
    $label = Label::factory()->create();
    $artist = Artist::factory()->create(['label_id' => $label->id]);
    $alias = Artist::factory()->create(['label_id' => $label->id, 'alias_of_id' => $artist->id]);
    $release = Release::factory()->create(['label_id' => $label->id, 'artist_id' => $artist->id]);

    $this->actingAs(labelAdmin($label))
        ->from(route('artists.index'))
        ->delete(route('artists.destroy', $artist->id))
        ->assertRedirect(route('artists.index'));

    $this->assertModelMissing($artist);
    $this->assertDatabaseHas('releases', ['id' => $release->id, 'artist_id' => null]);
    $this->assertDatabaseHas('artists', ['id' => $alias->id, 'alias_of_id' => null]);
});
