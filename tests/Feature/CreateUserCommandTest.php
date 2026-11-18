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
