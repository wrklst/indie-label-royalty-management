<?php

use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\Payee;
use App\Models\User;
use App\Notifications\LabelInvitation;
use App\Notifications\LoginCodeNotification;
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
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>alert("label")</script>')
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
