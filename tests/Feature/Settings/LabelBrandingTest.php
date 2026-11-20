<?php

use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('a logo and a signet are stored for the label and offered to its pages', function (string $kind) {
    $label = Label::factory()->create();
    $this->actingAs(labelAdmin($label));

    $this->post(route('label-branding.store', $kind), ['file' => UploadedFile::fake()->image("{$kind}.png", 200, 200)])
        ->assertOk()->assertContent($kind);

    $stored = $label->refresh()->{"{$kind}_path"};
    [$disk, $path] = explode(':', $stored, 2);

    expect($disk)->toBe('local')->and($path)->toStartWith("branding/{$label->id}/{$kind}-");
    Storage::disk('local')->assertExists($path);

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where("label.{$kind}_url", fn (?string $url): bool => str_contains((string) $url, "/label-branding/{$kind}?v=")));
    $this->get(route('label-branding.show', $kind))->assertOk()->assertHeader('Content-Type', 'image/png');
})->with(['logo', 'signet']);

test('a new image replaces the old one and removing deletes the file', function () {
    $label = Label::factory()->create();
    $this->actingAs(labelAdmin($label));

    $this->post(route('label-branding.store', 'logo'), ['file' => UploadedFile::fake()->image('first.png')])->assertOk();
    $first = explode(':', $label->refresh()->logo_path, 2)[1];
    $this->post(route('label-branding.store', 'logo'), ['file' => UploadedFile::fake()->image('second.jpg')])->assertOk();
    $second = explode(':', $label->refresh()->logo_path, 2)[1];

    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($second);

    $this->delete(route('label-branding.destroy', 'logo'))->assertRedirect();

    expect($label->refresh()->logo_path)->toBeNull();
    Storage::disk('local')->assertMissing($second);
    $this->get(route('label-branding.show', 'logo'))->assertNotFound();
});

test('a file that is no plain image is refused', function (Closure $file) {
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->postJson(route('label-branding.store', 'logo'), ['file' => $file()])
        ->assertJsonValidationErrors('file');

    expect($label->refresh()->logo_path)->toBeNull();
})->with([
    'an SVG, which can carry scripts' => [fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
    'a PDF' => [fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')],
    'an image above 4 MB' => [fn () => UploadedFile::fake()->image('logo.png')->size(4097)],
]);

test('payees see the images of their label but cannot change them, and other labels see nothing', function () {
    $label = Label::factory()->create();
    $this->actingAs(labelAdmin($label))->post(route('label-branding.store', 'logo'), ['file' => UploadedFile::fake()->image('logo.png')])->assertOk();
    $payee = User::factory()->create();
    $label->users()->attach($payee, ['role' => LabelRole::Payee->value]);

    $this->actingAs($payee)->get(route('label-branding.show', 'logo'))->assertOk();
    $this->actingAs($payee)->post(route('label-branding.store', 'logo'), ['file' => UploadedFile::fake()->image('other.png')])->assertForbidden();
    $this->actingAs($payee)->delete(route('label-branding.destroy', 'logo'))->assertForbidden();
    $this->actingAs(labelAdmin(Label::factory()->create()))->get(route('label-branding.show', 'logo'))->assertNotFound();

    expect($label->refresh()->logo_path)->not->toBeNull();
});

test('only a logo and a signet exist', function () {
    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->post('/label-branding/favicon', ['file' => UploadedFile::fake()->image('x.png')])->assertNotFound();
});
