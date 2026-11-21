<?php

use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\Payee;
use App\Models\Payout;
use App\Models\Period;
use App\Models\Statement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Royalties\PayoutService;
use App\Services\Royalties\StatementGenerator;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a user who sees the label's portal as the given payee.
 */
function portalPayee(Label $label, ?Payee $payee): User
{
    $user = User::factory()->create(['current_label_id' => $label->id]);
    $label->users()->attach($user, ['role' => LabelRole::Payee->value, 'payee_id' => $payee?->id]);

    return $user;
}

/**
 * A published statement whose snapshot says the payee is owed the given amount.
 *
 * @param  array<string, mixed>  $attributes
 */
function publishedStatement(Payee $payee, Period $period, string $owed, array $attributes = []): Statement
{
    return Statement::factory()->create([
        'label_id' => $payee->label_id,
        'payee_id' => $payee->id,
        'period_id' => $period->id,
        'published_at' => '2026-04-05 10:00:00',
        'balance' => $owed,
        'payable' => $owed,
        'data' => [
            'period' => ['id' => $period->id, 'name' => $period->name],
            'currency' => 'EUR',
            'totals' => ['payee_share' => $owed, 'total_paid' => '0', 'balance' => $owed, 'outstanding' => $owed],
            'ledgers' => [['ledger_id' => null, 'ledger_name' => 'Primary', 'balance' => $owed, 'unrecouped' => false]],
        ],
        ...$attributes,
    ]);
}

test('the overview shows the figures of the published statement, not the live ones', function () {
    $scenario = royaltyScenario();
    $statement = app(StatementGenerator::class)->generate($scenario['period'])->sole();
    $statement->update(['published_at' => '2026-04-05 10:00:00']);
    Transaction::factory()->create([
        'label_id' => $scenario['label']->id, 'release_id' => $scenario['release']->id, 'track_id' => $scenario['track']->id,
        'total' => '1000', 'timestamp' => '2026-02-28', 'paid_timestamp' => '2026-03-15',
    ]);

    $this->actingAs(portalPayee($scenario['label'], $scenario['payee']))
        ->get(route('portal.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/index')
            ->where('view', 'payee')
            ->where('overview', [
                'statement_id' => $statement->id,
                'period' => '2026 Q1',
                'published_at' => '2026-04-05',
                'currency' => 'EUR',
                'owed' => '50.000000000000000',
                'earned' => '50.000000000000000',
                'paid' => '0.000000000000000',
                'paid_since' => '0.000000000000000',
                'ledgers' => [['name' => 'Primary', 'balance' => '50.000000000000000', 'unrecouped' => false]],
            ]));
});

test('a regenerated draft does not change the overview until it is published', function () {
    $scenario = royaltyScenario();
    $generator = app(StatementGenerator::class);
    $published = $generator->generate($scenario['period'])->sole();
    $published->update(['published_at' => now()]);
    Transaction::factory()->create([
        'label_id' => $scenario['label']->id, 'release_id' => $scenario['release']->id, 'track_id' => $scenario['track']->id,
        'total' => '1000', 'timestamp' => '2026-02-28', 'paid_timestamp' => '2026-03-15',
    ]);
    $draft = $generator->generate($scenario['period'])->sole();

    expect($draft->payable)->toBe('550.000000000000000');

    $this->actingAs(portalPayee($scenario['label'], $scenario['payee']))
        ->get(route('portal.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.statement_id', $published->id)
            ->where('overview.owed', '50.000000000000000')
            ->has('statements.data', 1)
            ->where('statements.data.0.id', $published->id));
});

test('the overview follows the latest period, not the statement created last', function () {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $first = Period::factory()->create(['label_id' => $label->id, 'name' => '2026 Q1', 'starts_on' => '2026-01-01', 'ends_on' => '2026-03-31']);
    $second = Period::factory()->create(['label_id' => $label->id, 'name' => '2026 Q2', 'starts_on' => '2026-04-01', 'ends_on' => '2026-06-30']);
    $latest = publishedStatement($payee, $second, '80');
    publishedStatement($payee, $first, '30');

    $this->actingAs(portalPayee($label, $payee))
        ->get(route('portal.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.statement_id', $latest->id)
            ->where('overview.period', '2026 Q2')
            ->where('overview.owed', '80')
            ->has('statements.data', 2));
});

test('drafts, archived statements and other payees never feed the overview', function () {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $other = Payee::factory()->create(['label_id' => $label->id]);
    $first = Period::factory()->create(['label_id' => $label->id, 'name' => '2026 Q1']);
    $second = Period::factory()->create(['label_id' => $label->id, 'name' => '2026 Q2', 'starts_on' => '2026-04-01', 'ends_on' => '2026-06-30']);
    $own = publishedStatement($payee, $first, '30');
    publishedStatement($payee, $second, '111', ['published_at' => null]);
    publishedStatement($payee, $second, '222', ['archived' => true]);
    publishedStatement($other, $second, '333');

    $this->actingAs(portalPayee($label, $payee))
        ->get(route('portal.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.statement_id', $own->id)
            ->where('overview.period', '2026 Q1')
            ->where('overview.owed', '30')
            ->has('statements.data', 1));
});

test('a negative ledger is marked as unrecouped and does not reduce what is owed', function () {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $period = Period::factory()->create(['label_id' => $label->id]);
    publishedStatement($payee, $period, '50', ['data' => [
        'period' => ['name' => '2026 Q1'],
        'currency' => 'EUR',
        'totals' => ['payee_share' => '70', 'total_paid' => '20', 'balance' => '-150', 'outstanding' => '50'],
        'ledgers' => [
            ['ledger_id' => null, 'ledger_name' => 'Primary', 'balance' => '50'],
            ['ledger_id' => 7, 'ledger_name' => 'Album advance', 'balance' => '-200'],
        ],
    ]]);

    $this->actingAs(portalPayee($label, $payee))
        ->get(route('portal.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.owed', '50')
            ->where('overview.earned', '70')
            ->where('overview.paid', '20')
            ->where('overview.ledgers', [
                ['name' => 'Primary', 'balance' => '50', 'unrecouped' => false],
                ['name' => 'Album advance', 'balance' => '-200', 'unrecouped' => true],
            ]));
});

test('a statement without a snapshot falls back to its stored payable amount', function () {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $period = Period::factory()->create(['label_id' => $label->id, 'name' => '2026 Q1']);
    publishedStatement($payee, $period, '40', ['data' => []]);

    $this->actingAs(portalPayee($label, $payee))
        ->get(route('portal.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.period', '2026 Q1')
            ->where('overview.currency', null)
            ->where('overview.owed', '40.000000000000000')
            ->where('overview.ledgers', []));
});

test('a payee sees all of their own payouts, newest first', function () {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $other = Payee::factory()->create(['label_id' => $label->id]);
    $period = Period::factory()->create(['label_id' => $label->id, 'name' => '2026 Q1']);
    $older = Payout::factory()->create(['label_id' => $label->id, 'payee_id' => $payee->id, 'period_id' => $period->id, 'total' => '20', 'paid_on' => '2026-04-10', 'memo' => 'Bank transfer']);
    $newer = Payout::factory()->create(['label_id' => $label->id, 'payee_id' => $payee->id, 'period_id' => $period->id, 'total' => '30', 'paid_on' => '2026-05-02']);
    Payout::factory()->create(['label_id' => $label->id, 'payee_id' => $other->id, 'period_id' => $period->id, 'total' => '999']);

    $this->actingAs(portalPayee($label, $payee))
        ->get(route('portal.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payouts.data', [
                ['id' => $newer->id, 'paid_on' => '2026-05-02', 'period' => '2026 Q1', 'total' => '30.000000000000000', 'memo' => null],
                ['id' => $older->id, 'paid_on' => '2026-04-10', 'period' => '2026 Q1', 'total' => '20.000000000000000', 'memo' => 'Bank transfer'],
            ]));
});

test('a payout recorded after the statement is reported as paid since', function () {
    $scenario = royaltyScenario();
    $this->travelTo('2026-04-05 10:00:00');
    app(StatementGenerator::class)->generate($scenario['period'])->sole()->update(['published_at' => now()]);
    $this->travelTo('2026-04-10 09:00:00');
    app(PayoutService::class)->record($scenario['period'], $scenario['payee'], '20', '2026-04-10', 'Bank transfer', $scenario['admin']);

    $this->actingAs(portalPayee($scenario['label'], $scenario['payee']))
        ->get(route('portal.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.owed', '50.000000000000000')
            ->where('overview.paid_since', '20.000000000000000')
            ->has('payouts.data', 1)
            ->where('payouts.data.0.memo', 'Bank transfer'));
});

test('someone who is a payee of two labels only sees the label they are working in', function () {
    $label = Label::factory()->create();
    $otherLabel = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $otherPayee = Payee::factory()->create(['label_id' => $otherLabel->id]);
    $period = Period::factory()->create(['label_id' => $label->id, 'name' => '2026 Q1']);
    $otherPeriod = Period::factory()->create(['label_id' => $otherLabel->id, 'name' => '2026 Q4', 'starts_on' => '2026-10-01', 'ends_on' => '2026-12-31']);
    $own = publishedStatement($payee, $period, '30');
    $elsewhere = publishedStatement($otherPayee, $otherPeriod, '700');
    $payout = Payout::factory()->create(['label_id' => $label->id, 'payee_id' => $payee->id, 'period_id' => $period->id]);
    Payout::factory()->create(['label_id' => $otherLabel->id, 'payee_id' => $otherPayee->id, 'period_id' => $otherPeriod->id, 'total' => '700']);
    $user = portalPayee($label, $payee);
    $otherLabel->users()->attach($user, ['role' => LabelRole::Payee->value, 'payee_id' => $otherPayee->id]);

    $this->actingAs($user)
        ->get(route('portal.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.statement_id', $own->id)
            ->where('overview.owed', '30')
            ->has('payouts.data', 1)
            ->where('payouts.data.0.id', $payout->id)
            ->has('statements.data', 1)
            ->where('statements.data.0.id', $own->id));

    $this->actingAs($user)->get(route('portal.show', $elsewhere->id))->assertNotFound();
    $this->actingAs($user)->get(route('portal.download', $elsewhere->id))->assertNotFound();
});

test('the statement of another payee or label is not found', function () {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $other = Payee::factory()->create(['label_id' => $label->id]);
    $period = Period::factory()->create(['label_id' => $label->id]);
    $foreignPayee = Payee::factory()->create(['label_id' => Label::factory()]);
    $someoneElses = publishedStatement($other, $period, '10');
    $foreign = publishedStatement($foreignPayee, Period::factory()->create(['label_id' => $foreignPayee->label_id]), '10');
    $user = portalPayee($label, $payee);

    $this->actingAs($user)->get(route('portal.show', $someoneElses->id))->assertNotFound();
    $this->actingAs($user)->get(route('portal.download', $someoneElses->id))->assertNotFound();
    $this->actingAs($user)->get(route('portal.show', $foreign->id))->assertNotFound();
    $this->actingAs($user)->get(route('portal.download', $foreign->id))->assertNotFound();
});

test('a payee with nothing published gets an empty portal, not live figures', function () {
    $scenario = royaltyScenario();
    app(StatementGenerator::class)->generate($scenario['period']);

    $this->actingAs(portalPayee($scenario['label'], $scenario['payee']))
        ->get(route('portal.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', 'payee')
            ->where('overview', null)
            ->where('payouts.data', [])
            ->where('statements.data', []));
});

test('a payee user without a linked payee gets an explanation instead of figures', function () {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $period = Period::factory()->create(['label_id' => $label->id]);
    publishedStatement($payee, $period, '30');
    Payout::factory()->create(['label_id' => $label->id, 'payee_id' => $payee->id, 'period_id' => $period->id]);

    $this->actingAs(portalPayee($label, null))
        ->get(route('portal.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', 'unlinked')
            ->where('overview', null)
            ->where('payouts.data', [])
            ->where('statements.data', []));
});

test('an admin previews every published statement without a personal overview or payouts', function () {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $period = Period::factory()->create(['label_id' => $label->id]);
    $statement = publishedStatement($payee, $period, '30');
    publishedStatement(Payee::factory()->create(['label_id' => $label->id]), $period, '10');
    Payout::factory()->create(['label_id' => $label->id, 'payee_id' => $payee->id, 'period_id' => $period->id]);

    $this->actingAs(labelAdmin($label))
        ->get(route('portal.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', 'admin')
            ->where('overview', null)
            ->where('payouts.data', [])
            ->has('statements.data', 2)
            ->where('statements.data.1.id', $statement->id));
});
