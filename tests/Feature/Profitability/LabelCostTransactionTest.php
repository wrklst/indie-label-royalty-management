<?php

use App\Models\Label;
use App\Models\Payee;
use App\Models\Release;
use App\Models\Transaction;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * What the transaction form sends for an expense of 250.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function expensePayload(array $overrides = []): array
{
    return [
        'type' => 'expense',
        'timestamp' => '2026-02-01',
        'paid_timestamp' => '2026-02-15',
        'total' => '250',
        'currency' => 'EUR',
        'category' => 'Remix',
        ...$overrides,
    ];
}

test('a one-time payment is stored as an expense the label bears alone, with who received it', function () {
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id]);
    $payee = Payee::factory()->create(['label_id' => $label->id]);

    $this->actingAs(labelAdmin($label))
        ->post(route('transactions.store'), expensePayload(['release_id' => $release->id, 'payee_id' => $payee->id, 'recoupable' => false]))
        ->assertRedirect(route('transactions.index'));

    $this->assertDatabaseHas('transactions', [
        'label_id' => $label->id, 'type' => 'expense', 'release_id' => $release->id, 'payee_id' => $payee->id, 'recoupable' => false, 'converted_total' => -250,
    ]);
});

test('an expense is recouped from payees unless the form says otherwise', function () {
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->post(route('transactions.store'), expensePayload())
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('transactions', ['type' => 'expense', 'recoupable' => true, 'payee_id' => null]);
});

test('only an expense can be marked as not recoupable', function (string $type) {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);

    $this->actingAs(labelAdmin($label))
        ->post(route('transactions.store'), expensePayload(['type' => $type, 'payee_id' => $payee->id, 'recoupable' => false]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('transactions', ['type' => $type, 'recoupable' => true]);
})->with(['income', 'transfer']);

test('a recoupable flag that is no boolean is rejected', function () {
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->post(route('transactions.store'), expensePayload(['recoupable' => 'maybe']))
        ->assertSessionHasErrors(['recoupable' => 'The recoupable field must be true or false.']);

    $this->assertDatabaseCount('transactions', 0);
});

test('updating an expense can switch recouping on and off', function () {
    $label = Label::factory()->create();
    $transaction = Transaction::factory()->labelCost()->create(['label_id' => $label->id, 'total' => '-250']);
    $admin = labelAdmin($label);

    $this->actingAs($admin)->put(route('transactions.update', $transaction->id), expensePayload())->assertSessionHasNoErrors();

    expect($transaction->refresh()->recoupable)->toBeFalse();

    $this->actingAs($admin)->put(route('transactions.update', $transaction->id), expensePayload(['recoupable' => true]))->assertSessionHasNoErrors();

    expect($transaction->refresh()->recoupable)->toBeTrue();
});

test('turning a label cost into income makes it count in the split again', function () {
    $label = Label::factory()->create();
    $transaction = Transaction::factory()->labelCost()->create(['label_id' => $label->id, 'total' => '-250']);

    $this->actingAs(labelAdmin($label))
        ->put(route('transactions.update', $transaction->id), expensePayload(['type' => 'income', 'total' => '250', 'recoupable' => false]))
        ->assertSessionHasNoErrors();

    expect($transaction->refresh()->recoupable)->toBeTrue();
});

test('the one-time payment link opens the form as an expense with recouping switched off', function () {
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->get(route('transactions.create', ['type' => 'expense', 'recoupable' => 0]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('defaults', ['type' => 'expense', 'release_id' => null, 'payee_id' => null, 'recoupable' => false]));
});

test('the transaction list and the edit form tell label costs apart', function () {
    $label = Label::factory()->create();
    $cost = Transaction::factory()->labelCost()->create(['label_id' => $label->id, 'total' => '-250', 'timestamp' => '2026-02-02']);
    Transaction::factory()->create(['label_id' => $label->id, 'type' => 'expense', 'total' => '-40', 'timestamp' => '2026-02-01']);
    $admin = labelAdmin($label);

    $this->actingAs($admin)
        ->get(route('transactions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.data.0.recoupable', false)
            ->where('transactions.data.1.recoupable', true));

    $this->actingAs($admin)
        ->get(route('transactions.edit', $cost->id))
        ->assertInertia(fn (Assert $page) => $page->where('transaction.recoupable', false));
});
