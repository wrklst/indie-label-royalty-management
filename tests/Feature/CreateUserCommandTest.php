<?php

use App\Models\User;

test('an account is created verified and without a usable password', function () {
    $this->artisan('royalties:create-user', ['email' => 'Owner@Example.com', '--name' => 'Example Owner'])
        ->assertSuccessful();

    $user = User::query()->sole();

    expect($user->email)->toBe('owner@example.com')
        ->and($user->name)->toBe('Example Owner')
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

test('an account is not created for an invalid or taken address', function (string $email) {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('royalties:create-user', ['email' => $email])->assertFailed();

    $this->assertDatabaseCount('users', 1);
})->with(['not an address' => ['nobody'], 'taken, in another case' => ['Taken@example.com']]);

test('the installation admin role is handed to another account, and only one account has it afterwards', function () {
    $current = User::factory()->create(['installation_admin' => true]);
    $next = User::factory()->create(['email' => 'next@example.com']);

    $this->artisan('royalties:hand-over-installation', ['email' => 'Next@Example.com'])->assertSuccessful();

    expect($current->refresh()->installation_admin)->toBeFalse()
        ->and($next->refresh()->installation_admin)->toBeTrue();

    $this->artisan('royalties:hand-over-installation', ['email' => 'nobody@example.com'])->assertFailed();
});

test('the only installation admin cannot delete their account until the role is handed over', function () {
    $admin = User::factory()->create(['installation_admin' => true]);

    $this->actingAs($admin)->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('password');
    expect($admin->fresh())->not->toBeNull();

    $this->artisan('royalties:hand-over-installation', ['email' => User::factory()->create()->email])->assertSuccessful();

    $this->actingAs($admin->refresh())->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
    expect($admin->fresh())->toBeNull();
});
