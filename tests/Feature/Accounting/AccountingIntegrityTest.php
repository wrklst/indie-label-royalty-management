<?php

use App\Models\Contract;
use App\Models\Item;
use App\Models\Label;
use App\Models\Release;
use App\Models\Statement;
use App\Models\Track;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->label = Label::factory()->create();
    $this->admin = labelAdmin($this->label);
    $this->actingAs($this->admin);
});

function integrityTransaction(array $overrides = []): array
{
    return ['type' => 'income', 'timestamp' => '2026-02-01', 'paid_timestamp' => '2026-02-01', 'total' => '10', 'currency' => 'EUR', ...$overrides];
}

test('editing an imported transaction keeps the item it was booked on', function () {
    $release = Release::factory()->create(['label_id' => $this->label->id]);
    $contract = Contract::factory()->create(['label_id' => $this->label->id]);
    $item = Item::factory()->create(['label_id' => $this->label->id, 'release_id' => $release->id, 'contract_id' => $contract->id]);
    $transaction = Transaction::factory()->create(['label_id' => $this->label->id, 'release_id' => $release->id, 'item_id' => $item->id]);

    $this->put(route('transactions.update', $transaction->id), integrityTransaction(['release_id' => $release->id, 'memo' => 'Corrected memo']))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'memo' => 'Corrected memo', 'item_id' => $item->id]);
});

test('moving a transaction to another release books it on that release', function () {
    $old = Release::factory()->create(['label_id' => $this->label->id]);
    $new = Release::factory()->create(['label_id' => $this->label->id]);
    $item = Item::factory()->create(['label_id' => $this->label->id, 'release_id' => $old->id]);
    $transaction = Transaction::factory()->create(['label_id' => $this->label->id, 'release_id' => $old->id, 'item_id' => $item->id]);

    $this->put(route('transactions.update', $transaction->id), integrityTransaction(['release_id' => $new->id]))->assertSessionHasNoErrors();

    $additionals = Item::query()->withoutGlobalScopes()->where('release_id', $new->id)->where('is_additionals', true)->sole();
    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'release_id' => $new->id, 'item_id' => $additionals->id]);
});

test('saving a transaction without format and country keeps them', function () {
    $transaction = Transaction::factory()->create(['label_id' => $this->label->id, 'format' => 'Download Track', 'region' => 'DE']);

    $this->put(route('transactions.update', $transaction->id), integrityTransaction())->assertSessionHasNoErrors();

    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'format' => 'Download Track', 'region' => 'DE']);
});

test('amounts the ledger cannot hold are rejected', function (string $field, string $value) {
    $this->post(route('transactions.store'), integrityTransaction(['currency' => 'USD', $field => $value]))
        ->assertSessionHasErrors($field);

    $this->assertDatabaseCount('transactions', 0);
})->with([
    'a total beyond any number' => ['total', '1e400'],
    'a total with more decimals than are kept' => ['total', '0.0000000000000001'],
    'a conversion rate that would round to zero' => ['conversion_rate', '0.00000000000000000001'],
    'a quantity in exponent notation' => ['quantity', '1e30'],
]);

test('malformed list filters are refused instead of breaking the page', function (array $query) {
    $this->get(route('transactions.index', $query))->assertSessionHasErrors(array_key_first($query));
})->with([
    'a date that is not a date' => [['from' => 'garbage']],
    'a type sent as a list' => [['type' => ['a']]],
    'a search sent as a list' => [['search' => ['a']]],
]);

test('the dashboard refuses a malformed date range', function () {
    $this->get(route('dashboard', ['from' => 'garbage']))->assertSessionHasErrors('from');
});

test('search text is matched literally and an isrc is found however it is written', function () {
    $release = Release::factory()->create(['label_id' => $this->label->id]);
    $track = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $release->id, 'isrc' => 'ZZTST2600001']);
    Transaction::factory()->create(['label_id' => $this->label->id, 'release_id' => $release->id, 'track_id' => $track->id, 'memo' => 'By track']);
    Transaction::factory()->create(['label_id' => $this->label->id, 'memo' => '100% promo']);
    Transaction::factory()->create(['label_id' => $this->label->id, 'memo' => 'Something else']);

    $count = fn (string $search): int => $this->get(route('transactions.index', ['search' => $search]))->viewData('page')['props']['summary']['transactions'];

    expect($count('%'))->toBe(1)
        ->and($count('zz-tst-26-00001'))->toBe(1)
        ->and($count('_'))->toBe(0);
});

test('an unknown type in the address opens the form for income', function () {
    $this->get(route('transactions.create', ['type' => 'bogus']))
        ->assertInertia(fn ($page) => $page->where('defaults.type', 'income'));
});

test('an archived statement revision cannot be published', function () {
    $statement = Statement::factory()->create(['label_id' => $this->label->id, 'archived' => true]);

    $this->post(route('statements.publish', $statement->id))->assertStatus(422);

    expect($statement->refresh()->published_at)->toBeNull();
});

test('statement text that looks like a formula is neutralised in the download', function () {
    $statement = Statement::factory()->create(['label_id' => $this->label->id, 'data' => [
        'payee_name' => '=HYPERLINK("http://example.com")', 'period' => ['name' => '2026 Q1', 'starts_on' => '2026-01-01', 'ends_on' => '2026-03-31'],
        'currency' => 'EUR', 'totals' => ['balance' => '-5.000000000000000'], 'ledgers' => [], 'income_rows' => [], 'expense_rows' => [], 'transfer_rows' => [], 'payout_rows' => [],
    ]]);

    $csv = $this->get(route('statements.download', $statement->id))->streamedContent();

    expect($csv)->toContain("'=HYPERLINK")->toContain('-5.0000')->not->toContain("'-5.0000");
});

test('the accounting currency cannot change once the label has transactions', function () {
    Transaction::factory()->create(['label_id' => $this->label->id]);

    $this->put(route('label-settings.update'), ['name' => $this->label->name, 'currency' => 'USD'])
        ->assertSessionHasErrors('currency');

    expect($this->label->refresh()->currency)->toBe('EUR');
});

test('saving label settings keeps importer settings it does not manage', function () {
    $this->label->update(['importer_settings' => ['bandcamp' => ['band_id' => 12345], 'other' => ['enabled' => true]]]);

    $this->put(route('label-settings.update'), ['name' => $this->label->name, 'currency' => 'EUR', 'bandcamp_disable_fee' => '1'])->assertSessionHasNoErrors();

    expect($this->label->refresh()->importer_settings)->toBe([
        'bandcamp' => ['band_id' => 12345, 'disable_fee' => true, 'exclude_physical' => false],
        'other' => ['enabled' => true],
    ]);
});

test('territory codes are normalised however they are sent and a bad code is named', function () {
    $this->post(route('territories.store'), ['name' => 'GSA', 'countries' => ['de', 'AT', 'de']])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('territories', ['name' => 'GSA', 'countries' => json_encode(['DE', 'AT'])]);

    $this->post(route('territories.store'), ['name' => 'Broken', 'countries' => 'de, deu'])
        ->assertSessionHasErrors(['countries.1' => '"DEU" is not a two-letter country code such as DE.']);
});

test('removing someone who is not in the label is not found', function () {
    $this->delete(route('team.destroy', User::factory()->create()->id))->assertNotFound();
});

test('adding a member finds an existing account regardless of capitalisation', function () {
    $existing = User::factory()->create(['email' => 'Mixed.Case@example.com']);

    $this->post(route('team.store'), ['email' => 'mixed.case@example.com', 'role' => 'admin'])->assertSessionHasNoErrors();

    expect(User::query()->count())->toBe(2)
        ->and($this->label->users()->whereKey($existing->id)->exists())->toBeTrue();
});
