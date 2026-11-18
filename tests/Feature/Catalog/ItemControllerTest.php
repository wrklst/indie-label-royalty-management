<?php

use App\Models\Contract;
use App\Models\Item;
use App\Models\Label;
use App\Models\Release;
use App\Models\Territory;
use App\Models\Track;
use App\Models\Transaction;
use Inertia\Testing\AssertableInertia as Assert;

test('searching the item list matches name, ISRC, UPC and catalog number', function (string $search) {
    $label = Label::factory()->create();
    Item::factory()->create(['label_id' => $label->id, 'name' => 'Example Item', 'isrc' => 'ZZTST2600001', 'upc' => '4000000000017', 'catalog_number' => 'EX001']);
    Item::factory()->create(['label_id' => $label->id, 'name' => 'Sample Product', 'isrc' => 'ZZTST2600002', 'upc' => '4999999999994', 'catalog_number' => 'SMP900']);

    $this->actingAs(labelAdmin($label))
        ->get(route('items.index', ['search' => $search]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('items/index')
            ->has('items.data', 1)
            ->where('items.data.0.name', 'Example Item'));
})->with([
    'part of the name' => 'xample It',
    'ISRC' => 'ZZTST2600001',
    'UPC' => '4000000000017',
    'catalog number' => 'EX001',
]);

test('the item list puts unassigned items first and counts them', function () {
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release', 'catalog_number' => 'EX001']);
    Item::factory()->create(['label_id' => $label->id, 'name' => 'A Assigned Item', 'release_id' => $release->id]);
    Item::factory()->create(['label_id' => $label->id, 'name' => 'Z Unassigned Item']);

    $this->actingAs(labelAdmin($label))
        ->get(route('items.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 3)
            ->where('items.data.0.name', 'Z Unassigned Item')
            ->where('items.data.0.release', null)
            ->where('items.data.1.name', 'A Assigned Item')
            ->where('items.data.1.release', ['id' => $release->id, 'name' => 'Example Release', 'catalog_number' => 'EX001'])
            ->where('items.data.2.name', '[Additionals]')
            ->where('unassignedCount', 1));
});

test('the item list can be narrowed to unassigned items', function () {
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id]);
    Item::factory()->create(['label_id' => $label->id, 'name' => 'Assigned Item', 'release_id' => $release->id]);
    Item::factory()->create(['label_id' => $label->id, 'name' => 'Unassigned Item']);

    $this->actingAs(labelAdmin($label))
        ->get(route('items.index', ['unassigned' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.name', 'Unassigned Item')
            ->where('filters.unassigned', true));
});

test('the item list can be narrowed to one release', function () {
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id]);
    Release::factory()->create(['label_id' => $label->id]);
    Item::factory()->create(['label_id' => $label->id, 'name' => 'Example Item', 'release_id' => $release->id]);

    $this->actingAs(labelAdmin($label))
        ->get(route('items.index', ['release' => $release->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 2)
            ->where('items.data.0.release_id', $release->id)
            ->where('items.data.1.release_id', $release->id)
            ->where('filters.release', $release->id));
});

test('the item list shows how many transactions an item has and what they add up to', function () {
    $label = Label::factory()->create();
    $item = Item::factory()->create(['label_id' => $label->id, 'name' => 'Example Item']);
    Transaction::factory()->count(2)->create(['label_id' => $label->id, 'item_id' => $item->id, 'total' => '7.50']);
    Item::factory()->create(['label_id' => $label->id, 'name' => 'Unsold Item']);

    $this->actingAs(labelAdmin($label))
        ->get(route('items.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.data.0.name', 'Example Item')
            ->where('items.data.0.transactions_count', 2)
            ->where('items.data.0.total', fn (string $total) => bccomp($total, '15', 4) === 0)
            ->where('items.data.1.transactions_count', 0)
            ->where('items.data.1.total', '0'));
});

test('assigning an item to a track derives the release and moves its transactions along', function () {
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id]);
    $track = Track::factory()->create(['label_id' => $label->id, 'release_id' => $release->id]);
    $contract = Contract::factory()->create(['label_id' => $label->id]);
    $territory = Territory::factory()->create(['label_id' => $label->id]);
    $item = Item::factory()->create(['label_id' => $label->id]);
    $transactions = Transaction::factory()->count(2)->create(['label_id' => $label->id, 'item_id' => $item->id]);
    $unrelated = Transaction::factory()->create(['label_id' => $label->id]);

    $this->actingAs(labelAdmin($label))
        ->from(route('items.index'))
        ->put(route('items.update', $item->id), ['track_id' => $track->id, 'contract_id' => $contract->id, 'territory_id' => $territory->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('items.index'));

    $this->assertDatabaseHas('items', ['id' => $item->id, 'release_id' => $release->id, 'track_id' => $track->id, 'contract_id' => $contract->id, 'territory_id' => $territory->id]);
    $this->assertDatabaseHas('transactions', ['id' => $transactions[0]->id, 'release_id' => $release->id, 'track_id' => $track->id]);
    $this->assertDatabaseHas('transactions', ['id' => $transactions[1]->id, 'release_id' => $release->id, 'track_id' => $track->id]);
    $this->assertDatabaseHas('transactions', ['id' => $unrelated->id, 'release_id' => null, 'track_id' => null]);
});

test('the release of the chosen track wins over a different submitted release', function () {
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id]);
    $otherRelease = Release::factory()->create(['label_id' => $label->id]);
    $track = Track::factory()->create(['label_id' => $label->id, 'release_id' => $release->id]);
    $item = Item::factory()->create(['label_id' => $label->id]);

    $this->actingAs(labelAdmin($label))
        ->put(route('items.update', $item->id), ['release_id' => $otherRelease->id, 'track_id' => $track->id])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('items', ['id' => $item->id, 'release_id' => $release->id, 'track_id' => $track->id]);
});

test('assigning an item to a release only moves its transactions to the release level', function () {
    $label = Label::factory()->create();
    $oldRelease = Release::factory()->create(['label_id' => $label->id]);
    $oldTrack = Track::factory()->create(['label_id' => $label->id, 'release_id' => $oldRelease->id]);
    $release = Release::factory()->create(['label_id' => $label->id]);
    $item = Item::factory()->create(['label_id' => $label->id, 'release_id' => $oldRelease->id, 'track_id' => $oldTrack->id]);
    $transaction = Transaction::factory()->create(['label_id' => $label->id, 'item_id' => $item->id, 'release_id' => $oldRelease->id, 'track_id' => $oldTrack->id]);

    $this->actingAs(labelAdmin($label))
        ->put(route('items.update', $item->id), ['release_id' => $release->id, 'track_id' => ''])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('items', ['id' => $item->id, 'release_id' => $release->id, 'track_id' => null]);
    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'release_id' => $release->id, 'track_id' => null]);
});

test('clearing an item assignment unassigns its transactions', function () {
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id]);
    $item = Item::factory()->create(['label_id' => $label->id, 'release_id' => $release->id]);
    $transaction = Transaction::factory()->create(['label_id' => $label->id, 'item_id' => $item->id, 'release_id' => $release->id]);

    $this->actingAs(labelAdmin($label))
        ->put(route('items.update', $item->id), ['release_id' => '', 'track_id' => ''])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('items', ['id' => $item->id, 'release_id' => null, 'track_id' => null]);
    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'release_id' => null, 'track_id' => null]);
});

test('an item cannot be assigned to records of another label', function (string $field, string $model) {
    $label = Label::factory()->create();
    $item = Item::factory()->create(['label_id' => $label->id]);
    $transaction = Transaction::factory()->create(['label_id' => $label->id, 'item_id' => $item->id]);
    $foreign = $model::factory()->create(['label_id' => Label::factory()]);

    $this->actingAs(labelAdmin($label))
        ->put(route('items.update', $item->id), [$field => $foreign->id])
        ->assertSessionHasErrors($field);

    $this->assertDatabaseHas('items', ['id' => $item->id, 'release_id' => null, 'track_id' => null, 'contract_id' => null, 'territory_id' => null]);
    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'release_id' => null, 'track_id' => null]);
})->with([
    'release' => ['release_id', Release::class],
    'track' => ['track_id', Track::class],
    'contract' => ['contract_id', Contract::class],
    'territory' => ['territory_id', Territory::class],
]);

test('an item with transactions is not deleted', function () {
    $label = Label::factory()->create();
    $item = Item::factory()->create(['label_id' => $label->id]);
    Transaction::factory()->create(['label_id' => $label->id, 'item_id' => $item->id]);

    $this->actingAs(labelAdmin($label))
        ->from(route('items.index'))
        ->delete(route('items.destroy', $item->id))
        ->assertRedirect(route('items.index'));

    $this->assertModelExists($item);
});

test('an item without transactions is deleted', function () {
    $label = Label::factory()->create();
    $item = Item::factory()->create(['label_id' => $label->id]);

    $this->actingAs(labelAdmin($label))
        ->from(route('items.index'))
        ->delete(route('items.destroy', $item->id))
        ->assertRedirect(route('items.index'));

    $this->assertModelMissing($item);
});
