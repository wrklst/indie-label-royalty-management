<?php

use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\User;
use App\Services\Airplay\WebSearch;
use App\Support\InstallationSettings;
use App\Support\WorkingCopy;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const DATAFORSEO_ACCOUNT_URL = 'https://api.dataforseo.com/v3/appendix/user_data';
const INFOMANIAK_MODELS_URL = 'https://api.infomaniak.com/2/ai/12345/openai/v1/models';
const INFOMANIAK_CHAT_URL = 'https://api.infomaniak.com/2/ai/12345/openai/v1/chat/completions';

/**
 * What an OpenAI-compatible service answers to a chat request.
 */
function chatAnswer(string $text = 'OK'): array
{
    return ['id' => 'example', 'object' => 'chat.completion', 'model' => 'example-vision-model', 'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 1, 'total_tokens' => 6]];
}

test('the connections page shows what is set up without sending any secret back', function () {
    $label = Label::factory()->create([
        'dataforseo_login' => 'example-login', 'dataforseo_password' => 'example-password',
        'ai_provider' => 'infomaniak', 'ai_key' => 'example-key', 'ai_endpoint' => '12345', 'ai_model' => 'example-model',
    ]);

    $this->actingAs(labelAdmin($label))->get(route('connections.edit'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/connections')
            ->where('search', ['login' => 'example-login', 'password_set' => true, 'from_environment' => false])
            ->where('ai.provider', 'infomaniak')->where('ai.key_set', true)->where('ai.endpoint', '12345')->where('ai.model', 'example-model')
            ->where('ai.services.0.value', 'infomaniak')
            ->where('bandcamp.connected', false)
            ->where('storage', null))
        ->assertDontSee('example-password')
        ->assertDontSee('example-key');
});

test('a DataForSEO login is proven against the service and stored encrypted', function () {
    Http::preventStrayRequests();
    Http::fake([DATAFORSEO_ACCOUNT_URL => Http::response(['status_code' => 20000])]);
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->put(route('connections.search'), ['login' => 'example-login', 'password' => 'example-password'])
        ->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('example-login:example-password')));
    expect($label->refresh()->dataforseo_password)->toBe('example-password')
        ->and(DB::table('labels')->where('id', $label->id)->value('dataforseo_password'))->not->toContain('example-password');
});

test('a DataForSEO login the service refuses is not stored', function () {
    Http::preventStrayRequests();
    Http::fake([DATAFORSEO_ACCOUNT_URL => Http::response(['status_code' => 40100], 401)]);
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->put(route('connections.search'), ['login' => 'example-login', 'password' => 'wrong'])
        ->assertSessionHasErrors(['login' => 'DataForSEO does not accept this login and password.']);

    expect($label->refresh()->dataforseo_login)->toBeNull();
});

test('an empty DataForSEO password keeps the stored one and an empty login removes both', function () {
    Http::preventStrayRequests();
    $label = Label::factory()->create(['dataforseo_login' => 'example-login', 'dataforseo_password' => 'example-password']);
    $this->actingAs(labelAdmin($label));

    $this->put(route('connections.search'), ['login' => 'example-login', 'password' => ''])->assertSessionHasNoErrors();
    expect($label->refresh()->dataforseo_password)->toBe('example-password');

    $this->put(route('connections.search'), ['login' => '', 'password' => ''])->assertSessionHasNoErrors();
    expect($label->refresh()->only(['dataforseo_login', 'dataforseo_password']))->toBe(['dataforseo_login' => null, 'dataforseo_password' => null]);
});

test('a research uses the login of its label', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.dataforseo.com/v3/serp/*' => Http::response(['status_code' => 20000, 'tasks' => [['status_code' => 20000, 'result' => [['items' => []]]]]])]);
    $label = Label::factory()->create(['dataforseo_login' => 'label-login', 'dataforseo_password' => 'label-password']);

    app(WebSearch::class)->for($label)->search('"Example Artist" "Example Track"', 'DE');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('label-login:label-password')));
});

test('an Infomaniak connection is proven with a question to the chosen model and stored encrypted', function () {
    Http::preventStrayRequests();
    Http::fake([
        // The list names other models only; the model still answers, and that is what counts.
        INFOMANIAK_MODELS_URL => Http::response(['data' => [['id' => 'whisper'], ['id' => 'flux']]]),
        INFOMANIAK_CHAT_URL => Http::response(chatAnswer()),
    ]);
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->put(route('connections.ai'), ['provider' => 'infomaniak', 'key' => 'example-key', 'endpoint' => '12345', 'model' => 'example-vision-model'])
        ->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request): bool => $request->url() === INFOMANIAK_CHAT_URL
        && $request->hasHeader('Authorization', 'Bearer example-key') && $request['model'] === 'example-vision-model');
    expect($label->refresh()->only(['ai_provider', 'ai_key', 'ai_endpoint', 'ai_model']))
        ->toBe(['ai_provider' => 'infomaniak', 'ai_key' => 'example-key', 'ai_endpoint' => '12345', 'ai_model' => 'example-vision-model'])
        ->and(DB::table('labels')->where('id', $label->id)->value('ai_key'))->not->toContain('example-key');
});

test('an AI connection that cannot work is not stored', function (array $input, array $models, array $chat, string $field, string $message) {
    Http::preventStrayRequests();
    Http::fake([INFOMANIAK_MODELS_URL => Http::response(...$models), INFOMANIAK_CHAT_URL => Http::response(...$chat)]);
    $label = Label::factory()->create();

    $response = $this->actingAs(labelAdmin($label))
        ->put(route('connections.ai'), ['provider' => 'infomaniak', 'key' => 'example-key', 'endpoint' => '12345', 'model' => 'example-vision-model', ...$input]);

    $response->assertSessionHasErrors($field);
    expect(session('errors')->first($field))->toContain($message)
        ->and($label->refresh()->ai_provider)->toBeNull();
})->with([
    'a key the service refuses' => [[], [['error' => 'unauthorized'], 401], [chatAnswer()], 'key', 'The AI service does not accept this key and product ID.'],
    'a model that does not answer' => [[], [['data' => [['id' => 'whisper']]]], [['error' => ['message' => 'model not found']], 404], 'model', 'Models it lists: whisper'],
    'no key' => [['key' => ''], [['data' => []]], [chatAnswer()], 'key', 'Enter the API key of this service.'],
    'no product id' => [['endpoint' => ''], [['data' => []]], [chatAnswer()], 'endpoint', 'This service needs its Product ID.'],
    'no model' => [['model' => ''], [['data' => []]], [chatAnswer()], 'model', 'Name the model to read receipts with.'],
]);

test('switching the AI service asks for the key of the new one, and choosing none removes the connection', function () {
    Http::preventStrayRequests();
    $label = Label::factory()->create(['ai_provider' => 'infomaniak', 'ai_key' => 'example-key', 'ai_endpoint' => '12345', 'ai_model' => 'example-model']);
    $this->actingAs(labelAdmin($label));

    $this->put(route('connections.ai'), ['provider' => 'anthropic', 'key' => ''])->assertSessionHasErrors('key');
    expect($label->refresh()->ai_provider)->toBe('infomaniak');

    $this->put(route('connections.ai'), ['provider' => ''])->assertSessionHasNoErrors();
    expect($label->refresh()->only(['ai_provider', 'ai_key', 'ai_endpoint', 'ai_model']))
        ->toBe(['ai_provider' => null, 'ai_key' => null, 'ai_endpoint' => null, 'ai_model' => null]);
});

test('only an admin of the installation sees and changes the file storage', function () {
    $label = Label::factory()->create();
    $admin = labelAdmin($label);

    $this->actingAs($admin)->put(route('connections.storage'), ['disk' => 'local'])->assertForbidden();
    expect(InstallationSettings::storage())->toBeNull();

    $admin->forceFill(['installation_admin' => true])->save();

    $this->actingAs($admin)->get(route('connections.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('storage.disk', 'local')->where('storage.from_environment', true));
});

test('the file storage chosen in the interface takes effect and can be handed back to the environment', function () {
    $admin = labelAdmin(Label::factory()->create());
    $admin->forceFill(['installation_admin' => true])->save();
    $this->actingAs($admin);
    config(['royalties.storage.disk' => 's3']);

    $this->put(route('connections.storage'), ['disk' => 'local'])->assertSessionHasNoErrors();
    InstallationSettings::apply();

    expect(InstallationSettings::storage())->toBe(['disk' => 'local'])
        ->and(WorkingCopy::disk())->toBe('local')
        ->and(DB::table('installation_settings')->value('value'))->not->toContain('local');

    $this->put(route('connections.storage'), ['disk' => 'environment'])->assertSessionHasNoErrors();

    expect(InstallationSettings::storage())->toBeNull();
});

test('a bucket needs a name and keys before anything is stored', function (array $input, string $field) {
    config(['filesystems.disks.s3.key' => null, 'filesystems.disks.s3.secret' => null]);
    $admin = labelAdmin(Label::factory()->create());
    $admin->forceFill(['installation_admin' => true])->save();

    $this->actingAs($admin)->put(route('connections.storage'), ['disk' => 's3', ...$input])->assertSessionHasErrors($field);

    expect(InstallationSettings::storage())->toBeNull();
})->with([
    'no bucket' => [['key' => 'example-key', 'secret' => 'example-secret'], 'bucket'],
    'no keys' => [['bucket' => 'example-bucket'], 'key'],
    'an endpoint that is no https address' => [['bucket' => 'example-bucket', 'key' => 'example-key', 'secret' => 'example-secret', 'endpoint' => 'ftp://example.com'], 'endpoint'],
]);

test('a payee cannot reach the connections', function () {
    $label = Label::factory()->create();
    $payee = User::factory()->create();
    $label->users()->attach($payee, ['role' => LabelRole::Payee->value]);

    $this->actingAs($payee)->get(route('connections.edit'))->assertRedirect(route('portal.index'));
    $this->actingAs($payee)->put(route('connections.ai'), ['provider' => ''])->assertForbidden();
});

test('the first account created looks after the installation and later ones do not', function () {
    $this->artisan('royalties:create-user', ['email' => 'first@example.com'])->assertSuccessful();
    $this->artisan('royalties:create-user', ['email' => 'second@example.com'])->assertSuccessful();

    expect(User::query()->where('email', 'first@example.com')->sole()->installation_admin)->toBeTrue()
        ->and(User::query()->where('email', 'second@example.com')->sole()->installation_admin)->toBeFalse();
});
