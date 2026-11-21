<?php

use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\Payee;
use App\Models\Statement;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use App\Services\Royalties\PayoutService;
use App\Services\Royalties\StatementGenerator;
use Illuminate\Support\Facades\Notification;

test('a payee signs in with an emailed code and reads their overview, payouts and statement', function () {
    $scenario = royaltyScenario();
    app(StatementGenerator::class)->generate($scenario['period'])->sole()->update(['published_at' => now()]);
    $this->travel(1)->minutes();
    app(PayoutService::class)->record($scenario['period'], $scenario['payee'], '20', '2026-04-10', 'Bank transfer', $scenario['admin']);
    $user = User::factory()->create(['email' => 'artist@example.com']);
    $scenario['label']->users()->attach($user, ['role' => LabelRole::Payee->value, 'payee_id' => $scenario['payee']->id]);
    Notification::fake();

    $page = visit('/login')
        ->click('Email me a sign-in code')
        ->assertPathIs('/login/code')
        ->fill('email', 'artist@example.com')
        ->press('Email me a code')
        ->assertPathIs('/login/code/verify')
        ->assertSee('If an account exists for this address, we sent a code.')
        ->assertSee('artist@example.com');

    $code = Notification::sent($user, LoginCodeNotification::class)->sole()->code;

    $page->fill('code', '000000')
        ->press('Sign in')
        ->assertSee('This code is not valid or has expired.')
        ->fill('code', $code)
        ->press('Sign in')
        ->assertPathIs('/my-statements')
        ->assertSee('Owed to you')
        ->assertSee('As of 2026 Q1')
        ->assertSee('€50.00')
        ->assertSee('Balance per ledger')
        ->assertSee('Payouts')
        ->assertSee('Bank transfer')
        ->assertSee('€20.00')
        ->assertSee('My statements')
        ->assertDontSee('Transactions')
        ->click('a:has-text("2026 Q1")')
        ->assertSee('Example Payee – 2026 Q1')
        ->assertSee('Still payable')
        ->assertNoJavaScriptErrors();

    $this->assertAuthenticatedAs($user);
});

test('a payee with nothing published is told what will appear', function () {
    $scenario = royaltyScenario();
    $user = User::factory()->create(['current_label_id' => $scenario['label']->id]);
    $scenario['label']->users()->attach($user, ['role' => LabelRole::Payee->value, 'payee_id' => $scenario['payee']->id]);
    $this->actingAs($user);

    visit('/my-statements')
        ->assertSee('Nothing published yet')
        ->assertSee('As soon as this label publishes your first statement')
        ->assertDontSee('€50.00')
        ->assertNoJavaScriptErrors();
});

test('someone who runs a label and is a payee elsewhere sees both sections on My statements, and a titled sidebar group for it', function () {
    $mine = Label::factory()->create(['name' => 'Example Own Label']);
    $payee = Payee::factory()->create(['label_id' => $mine->id, 'name' => 'Example Payee One']);
    Statement::factory()->create(['label_id' => $mine->id, 'payee_id' => $payee->id, 'published_at' => now()]);
    $other = Label::factory()->create(['name' => 'Example Other Label']);
    $otherPayee = Payee::factory()->create(['label_id' => $other->id]);
    Statement::factory()->create(['label_id' => $other->id, 'payee_id' => $otherPayee->id, 'published_at' => now()]);
    $user = labelAdmin($mine);
    $other->users()->attach($user, ['role' => LabelRole::Payee->value, 'payee_id' => $otherPayee->id]);
    $this->actingAs($user);

    visit('/my-statements')
        ->assertSee('Your statements as a payee')
        ->assertSee('Example Other Label')
        ->assertSee('What the payees of Example Own Label see')
        ->assertSee('Example Payee One')
        ->assertSee('As a payee')
        ->assertNoJavaScriptErrors();
});

test('a new operator with no label yet is not headed as a payee', function () {
    $this->actingAs(User::factory()->create(['may_create_labels' => true]));

    visit('/labels/create')
        ->assertSee('Set up your label')
        ->assertSee('No label yet')
        ->assertDontSee('Payee')
        ->assertNoJavaScriptErrors();
});

test('someone removed from their last label is told so and can sign out', function () {
    $this->actingAs(User::factory()->create(['may_create_labels' => false]));

    visit('/labels/create')
        ->assertSee('You are not in a label')
        ->assertSee('Settings → Team')
        ->assertPresent('button:has-text("Log out")')
        ->assertNoJavaScriptErrors();
});
