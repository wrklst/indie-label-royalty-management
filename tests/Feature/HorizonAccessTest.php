<?php

use App\Models\Label;
use App\Models\User;

test('the queue dashboard is closed to everyone who is not listed', function () {
    config(['royalties.horizon_emails' => ['operator@example.com']]);

    $this->get('/horizon')->assertForbidden();
    $this->actingAs(labelAdmin(Label::factory()->create()))->get('/horizon')->assertForbidden();
});

test('a listed user can open the queue dashboard', function () {
    config(['royalties.horizon_emails' => ['operator@example.com']]);

    $this->actingAs(User::factory()->create(['email' => 'Operator@example.com']))
        ->get('/horizon')
        ->assertOk();
});

test('nobody is listed for the queue dashboard unless configured', function () {
    expect(config('royalties.horizon_emails'))->toBe([]);
});

test('a listed address that was never verified does not open the queue dashboard', function () {
    config(['royalties.horizon_emails' => ['operator@example.com']]);

    $this->actingAs(User::factory()->unverified()->create(['email' => 'operator@example.com']))
        ->get('/horizon')
        ->assertForbidden();
});
