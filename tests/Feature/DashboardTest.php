<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('users without a label are sent to create one', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertRedirect(route('labels.create'));
});

test('label admins can visit the dashboard', function () {
    $this->actingAs(labelAdmin(currentLabel()));

    $this->get(route('dashboard'))->assertOk();
});
