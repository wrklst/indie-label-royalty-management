<?php

use App\Models\Label;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email is stored in lowercase so the user can still log in', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'Test@Example.com',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBe('test@example.com');

    $this->post(route('logout'));

    $this->post(route('login.store'), [
        'email' => 'Test@Example.com',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('email must be unique regardless of case', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'Taken@Example.com',
        ]);

    $response
        ->assertSessionHasErrors('email')
        ->assertRedirect(route('profile.edit'));
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('a changed email address gets a confirmation link', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.com'])
        ->assertInertiaFlash('toast.message', 'Profile updated. Confirm the new address with the link we sent to it.');

    Notification::assertSentTo($user, VerifyEmail::class);
    expect($user->refresh()->email_verified_at)->toBeNull();
});

test('an unchanged email address gets no confirmation link', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Another Name', 'email' => $user->email]);

    Notification::assertNothingSent();
});

test('the only admin of a label cannot delete their account', function () {
    $label = Label::factory()->create(['name' => 'Example Records']);
    $admin = labelAdmin($label);

    $this->actingAs($admin)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors(['password' => 'You are the only admin of Example Records. Make someone else an admin under Settings → Team first.']);

    expect($admin->fresh())->not->toBeNull();
    $this->assertAuthenticatedAs($admin);
});

test('an admin can delete their account when the label has another admin', function () {
    $label = Label::factory()->create();
    $admin = labelAdmin($label);
    labelAdmin($label);

    $this->actingAs($admin)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasNoErrors();

    expect($admin->fresh())->toBeNull();
});
