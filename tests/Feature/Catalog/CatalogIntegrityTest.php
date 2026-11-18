<?php

use App\Models\Artist;
use App\Models\Contract;
use App\Models\ContractSplit;
use App\Models\Item;
use App\Models\Label;
use App\Models\Payee;
use App\Models\Release;
use App\Models\Territory;
use App\Models\Track;
use App\Models\Transaction;

beforeEach(function () {
    $this->label = Label::factory()->create();
    $this->actingAs(labelAdmin($this->label));
});

function contractPayload(array $splits, array $children = []): array
{
    return ['name' => 'Example Deal', 'splits' => $splits, 'children' => $children];
}

test('a split percentage must be a plain decimal the split can store', function (string $percentage) {
    $payee = Payee::factory()->create(['label_id' => $this->label->id]);

    $this->post(route('contracts.store'), contractPayload([['payee_id' => $payee->id, 'percentage' => $percentage]]))
        ->assertSessionHasErrors('splits.0.percentage');

    $this->assertDatabaseCount('contracts', 0);
})->with(['exponent notation' => '1e1', 'more decimals than a split keeps' => '0.00000000001']);

test('splits may not pay out more than the whole', function (array $percentages, bool $accepted) {
    $payee = Payee::factory()->create(['label_id' => $this->label->id]);
    $territories = Territory::factory()->count(2)->create(['label_id' => $this->label->id]);

    $splits = collect($percentages)->map(fn (array $split): array => [
        'payee_id' => $payee->id,
        'percentage' => $split[0],
        'territory_id' => $split[1] === null ? null : $territories[$split[1]]->id,
    ])->all();

    $response = $this->post(route('contracts.store'), contractPayload($splits));

    $accepted ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors(['splits' => 'The splits add up to more than 100%. A contract cannot pay out more than it earns.']);
    $this->assertDatabaseCount('contracts', $accepted ? 1 : 0);
})->with([
    'two worldwide splits over the whole' => [[['80', null], ['80', null]], false],
    'worldwide plus one territory over the whole' => [[['60', null], ['50', 0]], false],
    'exactly the whole' => [[['50', null], ['50', null]], true],
    'two territories that never apply together' => [[['40', null], ['60', 0], ['60', 1]], true],
]);

test('renaming a contract keeps its splits and child contracts', function () {
    $contract = Contract::factory()->create(['label_id' => $this->label->id]);
    $child = Contract::factory()->create(['label_id' => $this->label->id]);
    ContractSplit::factory()->create(['contract_id' => $contract->id, 'payee_id' => Payee::factory()->create(['label_id' => $this->label->id])]);
    $contract->children()->attach($child, ['weight' => 2]);

    $this->patch(route('contracts.update', $contract->id), ['name' => 'Renamed'])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'name' => 'Renamed']);
    $this->assertDatabaseCount('contract_splits', 1);
    $this->assertDatabaseCount('contract_links', 1);
});

test('a contract cannot be prorated across a contract that is prorated across it', function () {
    $parent = Contract::factory()->create(['label_id' => $this->label->id]);
    $middle = Contract::factory()->create(['label_id' => $this->label->id]);
    $leaf = Contract::factory()->create(['label_id' => $this->label->id]);
    $parent->children()->attach($middle, ['weight' => 1]);
    $middle->children()->attach($leaf, ['weight' => 1]);

    $this->put(route('contracts.update', $leaf->id), ['name' => $leaf->name, 'splits' => [], 'children' => [['contract_id' => $parent->id, 'weight' => '1']]])
        ->assertSessionHasErrors('children');

    $this->assertDatabaseMissing('contract_links', ['parent_contract_id' => $leaf->id]);
});

test('a child contract weight must fit the stored precision', function (string $weight) {
    $child = Contract::factory()->create(['label_id' => $this->label->id]);

    $this->post(route('contracts.store'), contractPayload([], [['contract_id' => $child->id, 'weight' => $weight]]))
        ->assertSessionHasErrors('children.0.weight');
})->with(['exponent notation' => '1e30', 'too large' => '1000000000']);

test('an alias must point at an artist that is not an alias itself', function () {
    $primary = Artist::factory()->create(['label_id' => $this->label->id]);
    $alias = Artist::factory()->create(['label_id' => $this->label->id, 'alias_of_id' => $primary->id]);

    $this->post(route('artists.store'), ['name' => 'Third Name', 'alias_of_id' => $alias->id])
        ->assertSessionHasErrors(['alias_of_id' => 'Choose an artist that is not itself an alias.']);

    $this->put(route('artists.update', $primary->id), ['name' => $primary->name, 'alias_of_id' => Artist::factory()->create(['label_id' => $this->label->id])->id])
        ->assertSessionHasErrors(['alias_of_id' => 'This artist has aliases of its own and cannot become an alias.']);
});

test('saving a track or release without its identifier keeps the identifier', function () {
    $release = Release::factory()->create(['label_id' => $this->label->id, 'upc' => '4000000000017']);
    $track = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $release->id, 'isrc' => 'ZZTST2600001']);

    $this->put(route('tracks.update', $track->id), ['name' => 'Renamed Track'])->assertSessionHasNoErrors();
    $this->put(route('releases.update', $release->id), ['name' => 'Renamed Release'])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('tracks', ['id' => $track->id, 'name' => 'Renamed Track', 'isrc' => 'ZZTST2600001']);
    $this->assertDatabaseHas('releases', ['id' => $release->id, 'name' => 'Renamed Release', 'upc' => '4000000000017']);
});

test('a malformed identifier is rejected instead of being dropped', function () {
    $release = Release::factory()->create(['label_id' => $this->label->id, 'upc' => '4000000000017']);
    $track = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $release->id, 'isrc' => 'ZZTST2600001']);

    $this->put(route('tracks.update', $track->id), ['name' => $track->name, 'isrc' => 'ZZTST26'])
        ->assertSessionHasErrors(['isrc' => 'The ISRC must have 12 letters and digits, for example DE-A1B-26-00001.']);
    $this->put(route('releases.update', $release->id), ['name' => $release->name, 'upc' => '12345'])
        ->assertSessionHasErrors(['upc' => 'The UPC / EAN must have 8 to 14 digits.']);
    $this->put(route('releases.update', $release->id), ['name' => $release->name, 'tracks' => [['id' => $track->id, 'name' => $track->name, 'isrc' => 'nonsense']]])
        ->assertSessionHasErrors('tracks.0.isrc');

    $this->assertDatabaseHas('tracks', ['id' => $track->id, 'isrc' => 'ZZTST2600001']);
    $this->assertDatabaseHas('releases', ['id' => $release->id, 'upc' => '4000000000017']);
});

test('a track of another release cannot be saved as a track of this one', function () {
    $release = Release::factory()->create(['label_id' => $this->label->id]);
    $foreign = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => Release::factory()->create(['label_id' => $this->label->id])]);

    $this->put(route('releases.update', $release->id), ['name' => $release->name, 'tracks' => [['id' => $foreign->id, 'name' => 'Copied']]])
        ->assertSessionHasErrors('tracks.0.id');

    $this->assertDatabaseCount('tracks', 1);
});

test('moving an item to another release drops the track it had on the old one', function () {
    $old = Release::factory()->create(['label_id' => $this->label->id]);
    $track = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $old->id]);
    $new = Release::factory()->create(['label_id' => $this->label->id]);
    $item = Item::factory()->create(['label_id' => $this->label->id, 'release_id' => $old->id, 'track_id' => $track->id]);
    $transaction = Transaction::factory()->create(['label_id' => $this->label->id, 'item_id' => $item->id, 'release_id' => $old->id, 'track_id' => $track->id]);

    $this->put(route('items.update', $item->id), ['release_id' => $new->id])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('items', ['id' => $item->id, 'release_id' => $new->id, 'track_id' => null]);
    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'release_id' => $new->id, 'track_id' => null]);
});

test('changing only the contract of an item leaves where its transactions are booked', function () {
    $release = Release::factory()->create(['label_id' => $this->label->id]);
    $item = Item::factory()->create(['label_id' => $this->label->id]);
    $transaction = Transaction::factory()->create(['label_id' => $this->label->id, 'item_id' => $item->id, 'release_id' => $release->id]);
    $contract = Contract::factory()->create(['label_id' => $this->label->id]);

    $this->put(route('items.update', $item->id), ['contract_id' => $contract->id])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('items', ['id' => $item->id, 'contract_id' => $contract->id]);
    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'release_id' => $release->id]);
});

test('the additionals item of a release cannot be reassigned or deleted', function () {
    $release = Release::factory()->create(['label_id' => $this->label->id]);
    $additionals = Item::query()->withoutGlobalScopes()->where('release_id', $release->id)->where('is_additionals', true)->sole();
    $other = Release::factory()->create(['label_id' => $this->label->id]);

    $this->put(route('items.update', $additionals->id), ['release_id' => $other->id])->assertForbidden();
    $this->delete(route('items.destroy', $additionals->id))->assertForbidden();

    $this->assertDatabaseHas('items', ['id' => $additionals->id, 'release_id' => $release->id]);
});

test('the additionals item follows the identifiers of its release', function () {
    $release = Release::factory()->create(['label_id' => $this->label->id, 'catalog_number' => 'EX001']);

    $this->put(route('releases.update', $release->id), ['name' => $release->name, 'catalog_number' => 'EX002', 'upc' => '4000000000024'])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('items', ['release_id' => $release->id, 'is_additionals' => true, 'catalog_number' => 'EX002', 'upc' => '4000000000024']);
});

test('deleting a track keeps its items that carry transactions on the release', function () {
    $release = Release::factory()->create(['label_id' => $this->label->id]);
    $track = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $release->id]);
    $used = Item::factory()->create(['label_id' => $this->label->id, 'release_id' => $release->id, 'track_id' => $track->id]);
    $empty = Item::factory()->create(['label_id' => $this->label->id, 'release_id' => $release->id, 'track_id' => $track->id]);
    $transaction = Transaction::factory()->create(['label_id' => $this->label->id, 'item_id' => $used->id, 'release_id' => $release->id, 'track_id' => $track->id]);

    $this->delete(route('tracks.destroy', $track->id))->assertRedirect();

    $this->assertDatabaseHas('items', ['id' => $used->id, 'release_id' => $release->id, 'track_id' => null]);
    $this->assertDatabaseMissing('items', ['id' => $empty->id]);
    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'item_id' => $used->id, 'release_id' => $release->id, 'track_id' => null]);
});

test('deleting a release leaves its income on unassigned items for reassignment', function () {
    $release = Release::factory()->create(['label_id' => $this->label->id, 'name' => 'Example Release']);
    $additionals = Item::query()->withoutGlobalScopes()->where('release_id', $release->id)->sole();
    $used = Item::factory()->create(['label_id' => $this->label->id, 'release_id' => $release->id, 'name' => 'Example Track']);
    $manual = Transaction::factory()->create(['label_id' => $this->label->id, 'item_id' => $additionals->id, 'release_id' => $release->id]);
    $imported = Transaction::factory()->create(['label_id' => $this->label->id, 'item_id' => $used->id, 'release_id' => $release->id]);

    $this->delete(route('releases.destroy', $release->id))->assertRedirect(route('releases.index'));

    $this->assertDatabaseHas('items', ['id' => $used->id, 'release_id' => null]);
    $this->assertDatabaseHas('items', ['id' => $additionals->id, 'release_id' => null, 'is_additionals' => false, 'name' => '[Additionals] Example Release']);
    $this->assertDatabaseHas('transactions', ['id' => $manual->id, 'item_id' => $additionals->id, 'release_id' => null]);
    $this->assertDatabaseHas('transactions', ['id' => $imported->id, 'item_id' => $used->id, 'release_id' => null]);
});

test('a payee who received transfers cannot be deleted', function () {
    $payee = Payee::factory()->create(['label_id' => $this->label->id]);
    Transaction::factory()->create(['label_id' => $this->label->id, 'type' => 'transfer', 'payee_id' => $payee->id, 'total' => '-100']);

    $this->delete(route('payees.destroy', $payee->id));

    $this->assertDatabaseHas('payees', ['id' => $payee->id]);
});

test('a upc or ean can only belong to one release of a label', function (string $entered) {
    $existing = Release::factory()->create(['label_id' => $this->label->id, 'name' => 'Example Release', 'upc' => '0700000000011']);
    $other = Release::factory()->create(['label_id' => $this->label->id, 'upc' => null]);

    $this->post(route('releases.store'), ['name' => 'Second Release', 'upc' => $entered])
        ->assertSessionHasErrors(['upc' => 'This UPC / EAN already belongs to the release "Example Release". A code identifies one release.']);
    $this->put(route('releases.update', $other->id), ['name' => $other->name, 'upc' => $entered])->assertSessionHasErrors('upc');

    $this->put(route('releases.update', $existing->id), ['name' => 'Renamed', 'upc' => $entered])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('releases', 2);
})->with(['the same code' => '0700000000011', 'its 12 digit form' => '700000000011', 'written with spaces' => '0 700000 000011']);

test('another label may use the same upc and several releases may have none', function () {
    Release::factory()->create(['label_id' => Label::factory(), 'upc' => '0700000000011']);

    $this->post(route('releases.store'), ['name' => 'Ours', 'upc' => '0700000000011'])->assertSessionHasNoErrors();
    $this->post(route('releases.store'), ['name' => 'No code yet'])->assertSessionHasNoErrors();
    $this->post(route('releases.store'), ['name' => 'No code either'])->assertSessionHasNoErrors();

    $this->assertDatabaseCount('releases', 4);
});
