<?php

use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\Payee;
use App\Models\Period;
use App\Models\Statement;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a user who may only see the statements of the given payee.
 */
function portalUser(Label $label, ?Payee $payee): User
{
    $user = User::factory()->create(['current_label_id' => $label->id]);
    $label->users()->attach($user, ['role' => LabelRole::Payee->value, 'payee_id' => $payee?->id]);

    return $user;
}

test('an admin sees the published statements of every payee in the portal', function () {
    $label = Label::factory()->create();
    $period = Period::factory()->create(['label_id' => $label->id, 'name' => '2026 Q1']);
    $first = Statement::factory()->create([
        'label_id' => $label->id, 'period_id' => $period->id, 'name' => 'Example Artist – 2026 Q1',
        'balance' => '70', 'payable' => '50', 'published_at' => '2026-04-05 10:00:00',
    ]);
    $second = Statement::factory()->create(['label_id' => $label->id, 'published_at' => '2026-04-06 10:00:00']);
    Statement::factory()->create(['label_id' => $label->id]);
    Statement::factory()->create(['label_id' => $label->id, 'published_at' => '2026-04-05 10:00:00', 'archived' => true]);
    Statement::factory()->create(['label_id' => Label::factory(), 'published_at' => '2026-04-05 10:00:00']);

    $this->actingAs(labelAdmin($label))
        ->get(route('portal.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/index')
            ->has('statements.data', 2)
            ->where('statements.data.0.id', $second->id)
            ->where('statements.data.1', [
                'id' => $first->id, 'name' => 'Example Artist – 2026 Q1', 'balance' => '70.000000000000000', 'payable' => '50.000000000000000',
                'period' => '2026 Q1', 'published_at' => '2026-04-05',
            ]));
});

test('an archived statement is hidden from the payee even though it was published', function () {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $archived = Statement::factory()->create(['label_id' => $label->id, 'payee_id' => $payee->id, 'published_at' => now(), 'archived' => true]);
    $user = portalUser($label, $payee);

    $this->actingAs($user)->get(route('portal.index'))
        ->assertInertia(fn (Assert $page) => $page->has('statements.data', 0));
    $this->actingAs($user)->get(route('portal.show', $archived->id))->assertNotFound();
    $this->actingAs($user)->get(route('portal.download', $archived->id))->assertNotFound();
});

test('a payee can open their own published statement', function () {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $statement = Statement::factory()->create([
        'label_id' => $label->id, 'payee_id' => $payee->id, 'name' => 'Example Artist – 2026 Q1', 'published_at' => now(),
        'data' => ['payee_name' => 'Example Artist'],
    ]);

    $this->actingAs(portalUser($label, $payee))
        ->get(route('portal.show', $statement->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('statements/show')
            ->where('statement.id', $statement->id)
            ->where('statement.name', 'Example Artist – 2026 Q1')
            ->where('statement.published', true)
            ->where('statement.data', ['payee_name' => 'Example Artist'])
            ->where('portal', true));
});

test('a payee can download their own published statement as csv', function () {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $statement = Statement::factory()->create([
        'label_id' => $label->id, 'payee_id' => $payee->id, 'name' => 'Example Artist – 2026 Q1', 'published_at' => now(),
        'data' => ['payee_name' => 'Example Artist', 'currency' => 'EUR', 'totals' => ['payee_share' => '50', 'balance' => '50']],
    ]);

    $response = $this->actingAs(portalUser($label, $payee))
        ->get(route('portal.download', $statement->id))
        ->assertOk()
        ->assertDownload('example-artist-2026-q1.csv');

    expect($response->streamedContent())
        ->toContain("Statement,\"Example Artist\"\n")
        ->toContain("0.0000,0.0000,0.0000,0.0000,50.0000,0.0000,0.0000,0.0000,50.0000\n");
});

test('a draft cannot be downloaded from the portal', function () {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $draft = Statement::factory()->create(['label_id' => $label->id, 'payee_id' => $payee->id]);

    $this->actingAs(portalUser($label, $payee))
        ->get(route('portal.download', $draft->id))
        ->assertNotFound();
});

test('a portal user without a linked payee sees no statements', function () {
    $label = Label::factory()->create();
    $statement = Statement::factory()->create(['label_id' => $label->id, 'published_at' => now()]);
    $user = portalUser($label, null);

    $this->actingAs($user)->get(route('portal.index'))
        ->assertInertia(fn (Assert $page) => $page->has('statements.data', 0));
    $this->actingAs($user)->get(route('portal.show', $statement->id))->assertNotFound();
});

test('a published statement of another label is not found in the portal', function () {
    $label = Label::factory()->create();
    $foreign = Statement::factory()->create(['label_id' => Label::factory(), 'published_at' => now()]);
    $admin = labelAdmin($label);

    $this->actingAs($admin)->get(route('portal.show', $foreign->id))->assertNotFound();
    $this->actingAs($admin)->get(route('portal.download', $foreign->id))->assertNotFound();
});

test('a guest is sent to the login page instead of the portal', function () {
    $this->get(route('portal.index'))->assertRedirect(route('login'));
});
