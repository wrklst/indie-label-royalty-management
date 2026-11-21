<?php

use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\Payee;
use App\Models\User;
use App\Notifications\LabelInvitation;
use App\Notifications\LoginCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

test('someone new gets an account and an invitation naming the label', function () {
    $label = Label::factory()->create(['name' => 'Example Records']);
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    Notification::fake();

    $this->actingAs(labelAdmin($label))
        ->post(route('team.store'), ['email' => 'New.Artist@Example.com', 'name' => 'New Artist', 'role' => 'payee', 'payee_id' => $payee->id])
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'new.artist@example.com')->sole();

    $this->assertDatabaseHas('label_user', ['label_id' => $label->id, 'user_id' => $user->id, 'role' => 'payee', 'payee_id' => $payee->id]);
    Notification::assertSentTo($user, fn (LabelInvitation $invitation): bool => $invitation->label->is($label));
    Notification::assertCount(1);
    $this->assertDatabaseCount('password_reset_tokens', 0);
});

test('an existing user who is added to a label gets the invitation too', function () {
    $label = Label::factory()->create(['name' => 'Example Records']);
    $existing = User::factory()->create(['email' => 'person@example.com']);
    Notification::fake();

    $this->actingAs(labelAdmin($label))
        ->post(route('team.store'), ['email' => 'person@example.com', 'role' => 'admin'])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($existing, fn (LabelInvitation $invitation): bool => $invitation->label->is($label));
    Notification::assertCount(1);
    $this->assertDatabaseCount('users', 2);
});

test('nobody is invited when the request is refused', function (array $input) {
    $label = Label::factory()->create();
    $member = User::factory()->create(['email' => 'member@example.com']);
    $label->users()->attach($member, ['role' => LabelRole::Admin->value]);
    Notification::fake();

    $this->actingAs(labelAdmin($label))->post(route('team.store'), $input);

    Notification::assertNothingSent();
})->with([
    'already a member' => [['email' => 'member@example.com', 'role' => 'admin']],
    'invalid input' => [['email' => 'new.person@example.com', 'role' => 'payee']],
]);

test('the invitation names the label and links to the sign-in code page with the email filled in', function () {
    $label = Label::factory()->create(['name' => 'Example Records']);
    $user = User::factory()->create(['name' => 'New Artist', 'email' => 'new.artist@example.com']);

    $mail = (new LabelInvitation($label))->toMail($user);
    $content = (string) $mail->render();

    expect($mail->subject)->toBe('Example Records gave you access to their royalty accounting')
        ->and($mail->actionText)->toBe('Sign in')
        ->and($mail->actionUrl)->toBe(route('login.code.create', ['email' => 'new.artist@example.com']))
        ->and($content)->toContain('Hello New Artist,')
        ->toContain('Example Records gave you access')
        ->toContain('You do not need a password')
        ->not->toContain('reset-password');
});

test('the invitation escapes the label and user name', function () {
    $label = Label::factory()->create(['name' => 'Example <script>alert("label")</script>']);
    $user = User::factory()->create(['name' => 'Artist <img src=x onerror=alert(1)>']);

    $content = (string) (new LabelInvitation($label))->toMail($user)->render();

    expect($content)
        ->toContain('Example script')
        ->not->toContain('<script>')
        ->not->toContain('<img src=x');
});

test('an invited person can sign in with a code from the invitation link without ever setting a password', function () {
    $label = Label::factory()->create();
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    Notification::fake();
    $this->actingAs(labelAdmin($label))
        ->post(route('team.store'), ['email' => 'new.artist@example.com', 'role' => 'payee', 'payee_id' => $payee->id]);
    $this->post(route('logout'));
    $user = User::query()->where('email', 'new.artist@example.com')->sole();
    $link = Notification::sent($user, LabelInvitation::class)->sole()->toMail($user)->actionUrl;

    // Days later: nothing in the invitation has a lifetime.
    $this->travel(30)->days();
    $this->get($link)->assertOk();
    $this->post(route('login.code.store'), ['email' => 'new.artist@example.com']);
    $code = Notification::sent($user, LoginCodeNotification::class)->sole()->code;
    $this->post(route('login.code.authenticate'), ['code' => $code])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    $this->get(route('portal.index'))->assertOk();
});

test('an account that took the invited address without proving it is locked to its owner before it is added', function () {
    $label = Label::factory()->create();
    $squatter = User::factory()->unverified()->create(['email' => 'manager@example.com']);
    $password = $squatter->password;
    DB::table('sessions')->insert(['id' => 'squatter-session', 'user_id' => $squatter->id, 'payload' => '', 'last_activity' => now()->timestamp]);
    config(['session.driver' => 'database']);
    Notification::fake();

    $this->actingAs(labelAdmin($label))->post(route('team.store'), ['email' => 'manager@example.com', 'role' => 'admin'])->assertSessionHasNoErrors();

    expect($squatter->refresh()->password)->not->toBe($password)
        ->and(DB::table('sessions')->where('user_id', $squatter->id)->exists())->toBeFalse()
        ->and($squatter->hasVerifiedEmail())->toBeFalse();
});

test('someone removed from the label they were invited to cannot open a label of their own', function () {
    $label = Label::factory()->create();
    Notification::fake();
    $this->actingAs(labelAdmin($label))->post(route('team.store'), ['email' => 'former@example.com', 'role' => 'admin'])->assertSessionHasNoErrors();
    $former = User::query()->where('email', 'former@example.com')->sole();
    $label->users()->detach($former->id);

    $this->actingAs($former)->post(route('labels.store'), ['name' => 'Example Records', 'currency' => 'EUR'])->assertForbidden();

    $this->assertDatabaseMissing('labels', ['name' => 'Example Records']);
});

test('a label or person named like a link does not become one in the invitation', function () {
    $label = Label::factory()->create(['name' => '[Confirm your bank details](https://example.com/phish)']);
    $user = User::factory()->create(['name' => '**Sir** <b>']);

    $mail = (new LabelInvitation($label))->toMail($user);

    expect($mail->introLines[0])->not->toContain('](')->not->toContain('[')
        ->and($mail->greeting)->toBe('Hello Sir b,');
});

test('every page tells the browser not to frame it elsewhere or guess content types', function () {
    $this->get(route('login'))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
});
