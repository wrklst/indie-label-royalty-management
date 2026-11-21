<?php

use App\Enums\LabelRole;
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
