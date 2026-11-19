<?php

use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    Str::createRandomStringsNormally();
});

test('the new label page tells whether it is the first label of the user', function (bool $hasLabel) {
    $user = $hasLabel ? labelAdmin(Label::factory()->create()) : User::factory()->create();

    $this->actingAs($user)
        ->get(route('labels.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('labels/create')
            ->where('isFirst', ! $hasLabel));
})->with([
    'user without a label' => [false],
    'user with a label' => [true],
]);

test('a new label gets a slug from its name and the email of its creator as contact', function () {
    $user = User::factory()->create(['email' => 'founder@example.com']);

    $this->actingAs($user)
        ->post(route('labels.store'), ['name' => 'Example Records', 'currency' => 'usd'])
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast.message', 'Label created.');

    $this->assertDatabaseHas('labels', ['name' => 'Example Records', 'slug' => 'example-records', 'currency' => 'USD', 'contact_email' => 'founder@example.com']);
});

test('a label with invalid input is not created', function (array $input, string $field) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('labels.store'), ['name' => 'Example Records', 'currency' => 'EUR', ...$input])
        ->assertSessionHasErrors($field);

    $this->assertDatabaseCount('labels', 0);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'current_label_id' => null]);
})->with([
    'no name' => [['name' => ''], 'name'],
    'name above 255 characters' => [['name' => str_repeat('a', 256)], 'name'],
    'no currency' => [['currency' => ''], 'currency'],
    'currency with four letters' => [['currency' => 'EURO'], 'currency'],
    'currency with two letters' => [['currency' => 'EU'], 'currency'],
    'currency with digits' => [['currency' => 'E1R'], 'currency'],
]);

test('a second label with the same name gets its own slug', function () {
    Label::factory()->create(['name' => 'Example Records', 'slug' => 'example-records']);
    $user = User::factory()->create();
    Str::createRandomStringsUsing(fn (): string => 'AbCdE');

    $this->actingAs($user)
        ->post(route('labels.store'), ['name' => 'Example Records', 'currency' => 'EUR'])
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseCount('labels', 2);
    $this->assertDatabaseHas('labels', ['name' => 'Example Records', 'slug' => 'example-records-abcde']);
});

test('a label whose name has no letters or digits gets a fallback slug', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('labels.store'), ['name' => '!!!', 'currency' => 'EUR'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('labels', ['name' => '!!!', 'slug' => 'label']);
});

test('a user with a label can create another one and is switched to it', function () {
    $first = Label::factory()->create();
    $user = labelAdmin($first);

    $this->actingAs($user)
        ->post(route('labels.store'), ['name' => 'Second Label', 'currency' => 'EUR'])
        ->assertRedirect(route('dashboard'));

    $second = Label::query()->where('name', 'Second Label')->sole();

    expect($user->refresh()->current_label_id)->toBe($second->id)
        ->and($user->labels()->pluck('labels.id')->all())->toEqualCanonicalizing([$first->id, $second->id]);
});

test('a guest cannot create a label', function () {
    $this->post(route('labels.store'), ['name' => 'Example Records', 'currency' => 'EUR'])->assertRedirect(route('login'));

    $this->assertDatabaseCount('labels', 0);
});

test('someone invited as a payee cannot create a label and is not offered to', function () {
    $label = Label::factory()->create();
    $payee = User::factory()->create();
    $label->users()->attach($payee, ['role' => LabelRole::Payee->value]);
    $this->actingAs($payee);

    $this->get(route('labels.create'))->assertForbidden();
    $this->post(route('labels.store'), ['name' => 'Example Records', 'currency' => 'EUR'])->assertForbidden();
    $this->get(route('portal.index'))->assertInertia(fn (Assert $page) => $page->where('canCreateLabel', false));

    $this->assertDatabaseMissing('labels', ['name' => 'Example Records']);
});

test('an admin is offered to add a label', function () {
    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('canCreateLabel', true));
});
