<?php

use App\Models\Contract;
use App\Models\ContractSplit;
use App\Models\Label;
use App\Models\Ledger;
use App\Models\Payee;
use Inertia\Testing\AssertableInertia as Assert;

test('the ledger list names the payees and contracts that book to each ledger once', function () {
    $label = Label::factory()->create();
    $ledger = Ledger::factory()->create(['label_id' => $label->id, 'name' => 'Example Ledger']);
    Ledger::factory()->create(['label_id' => $label->id, 'name' => 'Unused Ledger']);
    $payee = Payee::factory()->create(['label_id' => $label->id, 'name' => 'Example Payee']);
    $firstContract = Contract::factory()->create(['label_id' => $label->id, 'name' => 'Example Deal']);
    $secondContract = Contract::factory()->create(['label_id' => $label->id, 'name' => 'Sample Licence']);
    ContractSplit::factory()->create(['contract_id' => $firstContract->id, 'payee_id' => $payee->id, 'ledger_id' => $ledger->id]);
    ContractSplit::factory()->create(['contract_id' => $secondContract->id, 'payee_id' => $payee->id, 'ledger_id' => $ledger->id]);
    ContractSplit::factory()->create(['contract_id' => $secondContract->id, 'payee_id' => Payee::factory()->create(['label_id' => $label->id])]);

    $this->actingAs(labelAdmin($label))
        ->get(route('ledgers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ledgers/index')
            ->has('ledgers', 2)
            ->where('ledgers.0', [
                'id' => $ledger->id,
                'name' => 'Example Ledger',
                'splits_count' => 2,
                'payees' => ['Example Payee'],
                'contracts' => ['Example Deal', 'Sample Licence'],
            ])
            ->where('ledgers.1.name', 'Unused Ledger')
            ->where('ledgers.1.splits_count', 0)
            ->where('ledgers.1.payees', [])
            ->where('ledgers.1.contracts', []));
});

test('a ledger is created in the current label', function () {
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->from(route('ledgers.index'))
        ->post(route('ledgers.store'), ['name' => 'Example Ledger', 'label_id' => Label::factory()->create()->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('ledgers.index'));

    $this->assertDatabaseCount('ledgers', 1);
    $this->assertDatabaseHas('ledgers', ['label_id' => $label->id, 'name' => 'Example Ledger']);
});

test('a ledger cannot be created without a name', function () {
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->post(route('ledgers.store'), ['name' => ''])
        ->assertSessionHasErrors(['name' => 'The name field is required.']);

    $this->assertDatabaseCount('ledgers', 0);
});

test('a ledger is renamed', function () {
    $label = Label::factory()->create();
    $ledger = Ledger::factory()->create(['label_id' => $label->id, 'name' => 'Example Ledger']);

    $this->actingAs(labelAdmin($label))
        ->from(route('ledgers.index'))
        ->put(route('ledgers.update', $ledger->id), ['name' => 'Renamed Ledger'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('ledgers.index'))
        ->assertInertiaFlash('toast.message', 'Ledger saved.');

    $this->assertDatabaseHas('ledgers', ['id' => $ledger->id, 'name' => 'Renamed Ledger']);
});

test('a ledger cannot be renamed to nothing', function () {
    $label = Label::factory()->create();
    $ledger = Ledger::factory()->create(['label_id' => $label->id, 'name' => 'Example Ledger']);

    $this->actingAs(labelAdmin($label))
        ->put(route('ledgers.update', $ledger->id), ['name' => ''])
        ->assertSessionHasErrors('name');

    $this->assertDatabaseHas('ledgers', ['id' => $ledger->id, 'name' => 'Example Ledger']);
});

test('deleting a ledger keeps its splits without a ledger', function () {
    $label = Label::factory()->create();
    $ledger = Ledger::factory()->create(['label_id' => $label->id]);
    $split = ContractSplit::factory()->create([
        'contract_id' => Contract::factory()->create(['label_id' => $label->id]),
        'payee_id' => Payee::factory()->create(['label_id' => $label->id]),
        'ledger_id' => $ledger->id,
    ]);

    $this->actingAs(labelAdmin($label))
        ->from(route('ledgers.index'))
        ->delete(route('ledgers.destroy', $ledger->id))
        ->assertRedirect(route('ledgers.index'));

    $this->assertModelMissing($ledger);
    $this->assertDatabaseHas('contract_splits', ['id' => $split->id, 'ledger_id' => null]);
});
