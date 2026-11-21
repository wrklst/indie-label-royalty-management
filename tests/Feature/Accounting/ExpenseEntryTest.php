<?php

use App\Models\Label;
use App\Models\Payee;
use App\Models\Release;
use App\Models\Transaction;
use App\Services\Royalties\ProfitabilityReport;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * What the transaction form sends for an expense typed as the user types it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function typedExpense(array $overrides = []): array
{
    return ['type' => 'expense', 'timestamp' => '2026-02-01', 'paid_timestamp' => '2026-02-15', 'total' => '250', 'currency' => 'EUR', ...$overrides];
}

test('an expense typed as 250 is stored as -250 and counted against its release', function () {
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id]);

    $this->actingAs(labelAdmin($label))
        ->post(route('transactions.store'), typedExpense(['release_id' => $release->id]))
        ->assertSessionHasNoErrors();

    $expense = Transaction::query()->sole();

    expect($expense->total)->toBe('-250.000000000000000')
        ->and($expense->converted_total)->toBe('-250.000000000000000');

    $this->get(route('releases.show', $release->id))->assertInertia(fn (Assert $page) => $page->where('totals.expense', '-250.000000000000000'));
});

test('an expense typed with a minus sign is refused and says how to enter it', function () {
    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->post(route('transactions.store'), typedExpense(['total' => '-250']))
        ->assertSessionHasErrors(['total' => 'Enter an expense as a positive amount. For a refund or credit, tick “Refund or credit”.']);

    $this->assertDatabaseCount('transactions', 0);
});

test('a refund or credit is an expense stored positive, so it reduces the cost', function () {
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id]);
    $admin = labelAdmin($label);

    $this->actingAs($admin)->post(route('transactions.store'), typedExpense(['release_id' => $release->id]))->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('transactions.store'), typedExpense(['release_id' => $release->id, 'total' => '40', 'refund' => true]))->assertSessionHasNoErrors();

    expect(Transaction::query()->orderBy('id')->pluck('total')->all())->toBe(['-250.000000000000000', '40.000000000000000']);

    $this->get(route('releases.show', $release->id))->assertInertia(fn (Assert $page) => $page->where('totals.expense', '-210.000000000000000'));
});

test('editing an expense shows the positive amount and saving it again keeps the cost', function (string $stored, string $shown, bool $refund) {
    $label = Label::factory()->create();
    $transaction = Transaction::factory()->create(['label_id' => $label->id, 'type' => 'expense', 'total' => $stored, 'converted_total' => $stored]);
    $admin = labelAdmin($label);

    $this->actingAs($admin)
        ->get(route('transactions.edit', $transaction->id))
        ->assertInertia(fn (Assert $page) => $page->where('transaction.total', $shown)->where('transaction.refund', $refund));

    $this->actingAs($admin)
        ->put(route('transactions.update', $transaction->id), typedExpense(['total' => (string) (float) $shown, 'refund' => $refund]))
        ->assertSessionHasNoErrors();

    expect($transaction->refresh()->total)->toBe($stored)
        ->and($transaction->converted_total)->toBe($stored);
})->with([
    'a cost' => ['-250.000000000000000', '250.000000000000000', false],
    'a credit' => ['40.000000000000000', '40.000000000000000', true],
]);

test('income stays positive and a transfer keeps the sign it was typed with', function (string $type, string $typed, string $stored) {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $admin = labelAdmin($label);

    $this->actingAs($admin)
        ->post(route('transactions.store'), typedExpense(['type' => $type, 'total' => $typed, 'payee_id' => $payee->id]))
        ->assertSessionHasNoErrors();

    $transaction = Transaction::query()->sole();

    expect($transaction->total)->toBe($stored);

    $this->actingAs($admin)
        ->get(route('transactions.edit', $transaction->id))
        ->assertInertia(fn (Assert $page) => $page->where('transaction.total', $stored)->where('transaction.refund', false));
})->with([
    'income' => ['income', '100', '100.000000000000000'],
    'a transfer given to the payee' => ['transfer', '-500', '-500.000000000000000'],
    'a transfer owed by the payee' => ['transfer', '75', '75.000000000000000'],
]);

test('the refund flag means nothing for income and transfers', function (string $type) {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);

    $this->actingAs(labelAdmin($label))
        ->post(route('transactions.store'), typedExpense(['type' => $type, 'total' => '-30', 'refund' => true, 'payee_id' => $payee->id]))
        ->assertSessionHasNoErrors();

    expect(Transaction::query()->sole()->total)->toBe('-30.000000000000000');
})->with(['income', 'transfer']);

test('expenses already stored negative count exactly as before in a release and in profitability', function () {
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release']);
    Transaction::factory()->create(['label_id' => $label->id, 'release_id' => $release->id, 'type' => 'income', 'total' => '100', 'converted_total' => '100']);
    Transaction::factory()->create(['label_id' => $label->id, 'release_id' => $release->id, 'type' => 'expense', 'total' => '-40', 'converted_total' => '-40', 'recoupable' => false]);

    $this->actingAs(labelAdmin($label))
        ->get(route('releases.show', $release->id))
        ->assertInertia(fn (Assert $page) => $page->where('totals.income', '100.000000000000000')->where('totals.expense', '-40.000000000000000'));

    $report = app(ProfitabilityReport::class)->forLabel($label);

    expect($report['rows'][0]['label_costs'])->toBe('-40.000000000000000')
        ->and($report['totals']['label_result'])->toBe('60.000000000000000');
});

test('saving a transaction returns to the page it was opened from, and only to a local one', function (string $returnTo, bool $accepted) {
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id]);
    $admin = labelAdmin($label);

    $created = $this->actingAs($admin)->post(route('transactions.store'), typedExpense(['release_id' => $release->id, 'return_to' => $returnTo]));
    $created->assertRedirect($accepted ? $returnTo : route('transactions.index'));

    $this->actingAs($admin)
        ->put(route('transactions.update', Transaction::query()->sole()->id), typedExpense(['return_to' => $returnTo]))
        ->assertRedirect($accepted ? $returnTo : route('transactions.index'));
})->with([
    'the release' => ['/releases/7', true],
    'the filtered list' => ['/transactions?type=expense&release=7', true],
    'another host' => ['https://evil.example', false],
    'a scheme-less address' => ['//evil.example', false],
    'a backslash trick' => ['/\\evil.example', false],
    'a script' => ['javascript:alert(1)', false],
]);

test('the transaction form is handed the page it was opened from', function () {
    $label = Label::factory()->create();
    $transaction = Transaction::factory()->create(['label_id' => $label->id]);
    $admin = labelAdmin($label);

    $this->actingAs($admin)->get(route('transactions.create', ['type' => 'expense', 'return_to' => '/releases/7']))
        ->assertInertia(fn (Assert $page) => $page->where('returnTo', '/releases/7'));
    $this->actingAs($admin)->get(route('transactions.edit', ['transaction' => $transaction->id, 'return_to' => '//evil.example']))
        ->assertInertia(fn (Assert $page) => $page->where('returnTo', null));
});

test('changing the type of a saved transaction stores the amount the form shows under the new type', function (string $from, string $stored, string $to, string $typed, string $expected) {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $transaction = Transaction::factory()->create(['label_id' => $label->id, 'type' => $from, 'total' => $stored, 'converted_total' => $stored, 'currency' => 'EUR', 'payee_id' => $payee->id]);

    $this->actingAs(labelAdmin($label))
        ->put(route('transactions.update', $transaction->id), typedExpense(['type' => $to, 'total' => $typed, 'payee_id' => $payee->id]))
        ->assertSessionHasNoErrors();

    expect($transaction->refresh()->total)->toBe($expected)
        ->and($transaction->converted_total)->toBe($expected);
})->with([
    'a cost becomes income, shown as 250' => ['expense', '-250', 'income', '250', '250.000000000000000'],
    'a cost becomes a transfer, shown as -250 so money still goes out' => ['expense', '-250', 'transfer', '-250', '-250.000000000000000'],
    'income becomes a cost, typed 100' => ['income', '100', 'expense', '100', '-100.000000000000000'],
    'a transfer given out becomes a cost, typed 500' => ['transfer', '-500', 'expense', '500', '-500.000000000000000'],
    'a transfer becomes income as shown' => ['transfer', '75', 'income', '75', '75.000000000000000'],
    'income becomes a transfer as shown' => ['income', '100', 'transfer', '100', '100.000000000000000'],
]);
