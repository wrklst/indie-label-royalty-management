<?php

use App\Enums\LabelRole;
use App\Models\Contract;
use App\Models\ContractSplit;
use App\Models\Label;
use App\Models\Payee;
use App\Models\Period;
use App\Models\RecurringExpense;
use App\Models\Release;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Royalties\ProfitabilityReport;
use App\Services\Royalties\RecurringExpenseBooker;
use App\Services\Royalties\RoyaltyCalculator;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->label = Label::factory()->create();
    $this->actingAs(labelAdmin($this->label));
});

function bookedDates(RecurringExpense $expense): array
{
    return Transaction::query()->withoutGlobalScopes()->where('recurring_expense_id', $expense->id)
        ->orderBy('paid_timestamp')->get()->map(fn (Transaction $t): string => $t->paid_timestamp->toDateString())->all();
}

test('a one-time cost is booked as an expense the label bears alone', function () {
    $this->post(route('label-costs.store'), ['name' => 'Example Hosting', 'amount' => '120', 'currency' => 'eur', 'category' => 'Hosting', 'paid_on' => '2026-03-01'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('transactions', [
        'label_id' => $this->label->id, 'type' => 'expense', 'recoupable' => false, 'release_id' => null, 'source' => 'Example Hosting',
        'category' => 'Hosting', 'currency' => 'EUR', 'paid_timestamp' => '2026-03-01',
    ]);

    expect(Transaction::query()->withoutGlobalScopes()->sole()->converted_total)->toBe('-120.000000000000000');
});

test('a cost in another currency needs a rate to be counted', function () {
    $this->post(route('label-costs.store'), ['name' => 'Example Tool', 'amount' => '10', 'currency' => 'USD', 'paid_on' => '2026-03-01']);
    $this->post(route('label-costs.store'), ['name' => 'Example Tool', 'amount' => '10', 'currency' => 'USD', 'conversion_rate' => '0.9', 'paid_on' => '2026-03-02']);

    $costs = Transaction::query()->withoutGlobalScopes()->orderBy('paid_timestamp')->get();

    expect($costs[0]->converted_total)->toBeNull()
        ->and($costs[1]->converted_total)->toBe('-9.000000000000000');
});

test('a label cost is refused when it is not a positive amount with a currency', function (array $input, string $field) {
    $this->post(route('label-costs.store'), ['name' => 'Example', 'amount' => '10', 'currency' => 'EUR', 'paid_on' => '2026-03-01', ...$input])
        ->assertSessionHasErrors($field);

    $this->assertDatabaseCount('transactions', 0);
})->with([
    'a negative amount' => [['amount' => '-10'], 'amount'],
    'zero' => [['amount' => '0'], 'amount'],
    'no name' => [['name' => ''], 'name'],
    'a currency that is not a code' => [['currency' => 'EURO'], 'currency'],
    'no date' => [['paid_on' => ''], 'paid_on'],
]);

test('a recurring cost books every due date up to today and none in the future', function () {
    $this->travelTo('2026-04-20');

    $this->post(route('label-costs.recurring.store'), ['name' => 'Example Subscription', 'amount' => '10', 'currency' => 'EUR', 'interval' => 'monthly', 'starts_on' => '2026-01-15'])
        ->assertSessionHasNoErrors();

    $expense = RecurringExpense::query()->withoutGlobalScopes()->sole();

    expect(bookedDates($expense))->toBe(['2026-01-15', '2026-02-15', '2026-03-15', '2026-04-15'])
        ->and($expense->occurrences_booked)->toBe(4)
        ->and($expense->nextDueOn()->toDateString())->toBe('2026-05-15');
});

test('booking again does not book a due date twice', function () {
    $expense = RecurringExpense::factory()->create(['label_id' => $this->label->id, 'starts_on' => '2026-01-15']);
    $booker = app(RecurringExpenseBooker::class);

    expect($booker->bookDue($this->label, CarbonImmutable::parse('2026-03-20')))->toBe(3)
        ->and($booker->bookDue($this->label, CarbonImmutable::parse('2026-03-20')))->toBe(0)
        ->and($booker->bookDue($this->label, CarbonImmutable::parse('2026-04-15')))->toBe(1)
        ->and(bookedDates($expense))->toHaveCount(4);
});

test('due dates follow the interval and stay at the end of short months', function (string $interval, string $start, string $until, array $expected) {
    $expense = RecurringExpense::factory()->create(['label_id' => $this->label->id, 'interval' => $interval, 'starts_on' => $start]);

    app(RecurringExpenseBooker::class)->bookDue($this->label, CarbonImmutable::parse($until));

    expect(bookedDates($expense))->toBe($expected);
})->with([
    'monthly from the 31st' => ['monthly', '2026-01-31', '2026-04-30', ['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30']],
    'quarterly' => ['quarterly', '2026-01-10', '2026-08-01', ['2026-01-10', '2026-04-10', '2026-07-10']],
    'yearly' => ['yearly', '2024-06-01', '2026-06-01', ['2024-06-01', '2025-06-01', '2026-06-01']],
]);

test('a recurring cost stops after its last payment date', function () {
    $expense = RecurringExpense::factory()->create(['label_id' => $this->label->id, 'starts_on' => '2026-01-15', 'ends_on' => '2026-02-20']);

    app(RecurringExpenseBooker::class)->bookDue($this->label, CarbonImmutable::parse('2026-12-31'));

    expect(bookedDates($expense))->toBe(['2026-01-15', '2026-02-15'])
        ->and($expense->refresh()->nextDueOn())->toBeNull();
});

test('changing a recurring cost applies to later payments only', function () {
    $this->travelTo('2026-02-20');
    $expense = RecurringExpense::factory()->create(['label_id' => $this->label->id, 'starts_on' => '2026-01-15', 'amount' => '10']);
    app(RecurringExpenseBooker::class)->bookDue($this->label);

    $this->put(route('label-costs.recurring.update', $expense->id), ['name' => 'Example subscription', 'amount' => '15', 'currency' => 'EUR'])->assertSessionHasNoErrors();
    $this->travelTo('2026-03-20');
    app(RecurringExpenseBooker::class)->bookDue($this->label);

    $totals = Transaction::query()->withoutGlobalScopes()->orderBy('paid_timestamp')->pluck('total')->all();

    expect($totals)->toBe(['-10.000000000000000', '-10.000000000000000', '-15.000000000000000']);
});

test('stopping a recurring cost keeps what was booked', function () {
    $expense = RecurringExpense::factory()->create(['label_id' => $this->label->id, 'starts_on' => '2026-01-15']);
    app(RecurringExpenseBooker::class)->bookDue($this->label, CarbonImmutable::parse('2026-02-20'));

    $this->delete(route('label-costs.recurring.destroy', $expense->id))->assertRedirect();

    $this->assertDatabaseCount('recurring_expenses', 0);
    $this->assertDatabaseCount('transactions', 2);
    $this->assertDatabaseHas('transactions', ['recurring_expense_id' => null]);
});

test('a recurring cost needs a known interval and an end after its start', function (array $input, string $field) {
    $this->post(route('label-costs.recurring.store'), ['name' => 'Example', 'amount' => '10', 'currency' => 'EUR', 'interval' => 'monthly', 'starts_on' => '2026-01-15', ...$input])
        ->assertSessionHasErrors($field);

    $this->assertDatabaseCount('recurring_expenses', 0);
})->with([
    'an unknown interval' => [['interval' => 'weekly'], 'interval'],
    'an end before the start' => [['ends_on' => '2026-01-01'], 'ends_on'],
    'a start in the distant past' => [['starts_on' => '1900-01-01'], 'starts_on'],
]);

test('the scheduled command books due costs of every label', function () {
    $this->travelTo('2026-02-20');
    RecurringExpense::factory()->create(['label_id' => $this->label->id, 'starts_on' => '2026-01-15']);
    RecurringExpense::factory()->create(['label_id' => Label::factory(), 'starts_on' => '2026-02-01']);

    $this->artisan('royalties:book-recurring-expenses')->assertSuccessful();

    $this->assertDatabaseCount('transactions', 3);
});

test('the page lists recurring and booked costs of this label only and books what is due', function () {
    $this->travelTo('2026-02-20');
    RecurringExpense::factory()->create(['label_id' => $this->label->id, 'name' => 'Ours', 'starts_on' => '2026-01-15']);
    RecurringExpense::factory()->create(['label_id' => Label::factory(), 'name' => 'Theirs']);
    Transaction::factory()->labelCost()->create(['label_id' => $this->label->id, 'total' => '-5', 'release_id' => Release::factory()->create(['label_id' => $this->label->id])]);

    $this->get(route('label-costs.index'))->assertInertia(fn (Assert $page) => $page
        ->component('label-costs/index')
        ->has('recurring', 1)
        ->where('recurring.0.name', 'Ours')
        ->where('recurring.0.next_due_on', '2026-03-15')
        ->has('costs', 2)
        ->where('costs.0.recurring', true)
        ->where('totals.this_year', '-20.000000000000000'));
});

test('a recurring cost of another label cannot be changed or stopped', function () {
    $foreign = RecurringExpense::factory()->create(['label_id' => Label::factory()]);

    $this->put(route('label-costs.recurring.update', $foreign->id), ['name' => 'Hijacked', 'amount' => '1', 'currency' => 'EUR'])->assertNotFound();
    $this->delete(route('label-costs.recurring.destroy', $foreign->id))->assertNotFound();

    $this->assertDatabaseHas('recurring_expenses', ['id' => $foreign->id, 'name' => 'Example subscription']);
});

test('payees cannot see or book label costs', function () {
    $user = User::factory()->create(['current_label_id' => $this->label->id]);
    $this->label->users()->attach($user, ['role' => LabelRole::Payee->value, 'payee_id' => Payee::factory()->create(['label_id' => $this->label->id])->id]);

    $this->actingAs($user)->get(route('label-costs.index'))->assertRedirect(route('portal.index'));
    $this->actingAs($user)->post(route('label-costs.store'), ['name' => 'x', 'amount' => '1', 'currency' => 'EUR', 'paid_on' => '2026-01-01'])->assertForbidden();
});

test('label costs lower the label result as overheads and leave royalties untouched', function () {
    $payee = Payee::factory()->create(['label_id' => $this->label->id]);
    $contract = Contract::factory()->create(['label_id' => $this->label->id]);
    ContractSplit::factory()->create(['contract_id' => $contract->id, 'payee_id' => $payee->id, 'split_amount' => '0.5']);
    $release = Release::factory()->create(['label_id' => $this->label->id, 'contract_id' => $contract->id, 'name' => 'Example Release']);
    Transaction::factory()->create(['label_id' => $this->label->id, 'release_id' => $release->id, 'total' => '100']);
    $period = Period::factory()->create(['label_id' => $this->label->id]);

    $this->post(route('label-costs.store'), ['name' => 'Example Hosting', 'amount' => '30', 'currency' => 'EUR', 'paid_on' => '2026-02-01']);

    $report = app(ProfitabilityReport::class)->forLabel($this->label);
    $royalties = app(RoyaltyCalculator::class)->forPeriod($period);

    expect(array_column($report['rows'], 'release_name'))->toBe(['Example Release', 'Label overheads'])
        ->and($report['rows'][1]['label_costs'])->toBe('-30.000000000000000')
        ->and($report['totals']['label_result'])->toBe('20.000000000000000')
        ->and($royalties['payees'][0]['totals']['payee_share'])->toBe('50.000000000000000')
        ->and($royalties['audit']['unassigned'])->toBe(0)
        ->and($royalties['totals']['label_costs'])->toBe('-30.000000000000000');
});
