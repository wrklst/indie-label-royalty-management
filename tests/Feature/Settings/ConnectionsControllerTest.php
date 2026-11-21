<?php

use App\Ai\Agents\ReceiptReader;
use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\User;
use App\Services\Airplay\WebSearch;
use App\Services\Receipts\ModelCatalogue;
use App\Services\Receipts\SampleReceipt;
use App\Support\InstallationSettings;
use App\Support\PublicAddress;
use App\Support\WorkingCopy;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const DATAFORSEO_ACCOUNT_URL = 'https://api.dataforseo.com/v3/appendix/user_data';
const INFOMANIAK_MODELS_URL = 'https://api.infomaniak.com/2/ai/12345/openai/v1/models';
const CATALOGUE_URL = 'https://models.dev/api.json';

/**
 * The public catalogue with two Infomaniak models that read pictures, one that does not and one that is retired.
 */
function catalogue(): array
{
    $model = fn (string $name, array $input, float $in, float $out, array $more = []): array => [
        'name' => $name, 'description' => "Example description of {$name}", 'modalities' => ['input' => $input, 'output' => ['text']],
        'cost' => ['input' => $in, 'output' => $out], 'release_date' => '2026-03-16', ...$more,
    ];

    return ['infomaniak' => ['models' => [
        'example/large-vision' => $model('Large Vision', ['text', 'image'], 1.0, 4.0, ['structured_output' => true, 'reasoning' => true]),
        'example/small-vision' => $model('Small Vision', ['text', 'image'], 0.25, 0.5),
        'example/text-only' => $model('Text Only', ['text'], 0.1, 0.2),
        'example/retired-vision' => $model('Retired Vision', ['text', 'image'], 0.1, 0.2, ['status' => 'deprecated']),
        'example/embedding' => $model('Embedding', ['text', 'image'], 0.1, 0),
    ]]];
}

function sampleReading(array $overrides = []): array
{
    return [...SampleReceipt::EXPECTED, 'category' => 'Hosting', 'summary' => 'Web hosting, March', ...$overrides];
}

beforeEach(function () {
    Cache::forget('receipt-models');
    $this->catalogueIsDown = false;
    Http::fake([CATALOGUE_URL => fn () => $this->catalogueIsDown ? Http::response('unavailable', 503) : Http::response(catalogue())]);
});

test('the catalogue offers the models of a service that take pictures, cheapest first, with what a hundred receipts cost', function () {
    expect(ModelCatalogue::all()['infomaniak'])->toBe([
        ['id' => 'example/small-vision', 'name' => 'Small Vision', 'description' => 'Example description of Small Vision', 'input_price' => 0.25, 'output_price' => 0.5, 'per_hundred' => 0.08, 'fixed_format' => false, 'reasoning' => false, 'released' => '2026-03-16'],
        ['id' => 'example/large-vision', 'name' => 'Large Vision', 'description' => 'Example description of Large Vision', 'input_price' => 1.0, 'output_price' => 4.0, 'per_hundred' => 0.36, 'fixed_format' => true, 'reasoning' => true, 'released' => '2026-03-16'],
    ]);

    ModelCatalogue::all();
    Http::assertSentCount(1);
});

test('a catalogue that cannot be reached leaves the model to be typed in', function () {
    $this->catalogueIsDown = true;

    expect(ModelCatalogue::all())->toBe([]);
});

test('the connections page shows stored connections as hints, never the secrets', function () {
    $label = Label::factory()->create([
        'dataforseo_login' => 'example-login@example.com', 'dataforseo_password' => 'example-password',
        'ai_provider' => 'infomaniak', 'ai_key' => 'example-key-9f3a', 'ai_endpoint' => '12345', 'ai_model' => 'example/small-vision',
        'ai_trial' => ['seconds' => 3.2, 'missed' => [], 'at' => '2026-11-19T12:00:00+00:00'],
    ]);

    $this->actingAs(labelAdmin($label))->get(route('connections.edit'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/connections')
            ->where('search', ['airplay_markets' => ['DE', 'GB', 'US'], 'connected' => true, 'login' => 'exa•••••••••••••••••••com', 'from_environment' => false])
            ->where('ai.connected', true)->where('ai.service', 'Infomaniak AI Tools')->where('ai.key_hint', '••••9f3a')
            ->where('ai.endpoint_label', 'Product ID')->where('ai.endpoint', '12345')->where('ai.model', 'example/small-vision')
            ->where('ai.trial.seconds', 3.2)
            ->where('ai.catalogue.infomaniak.0.id', 'example/small-vision')
            ->where('bandcamp.connected', false)
            ->where('storage', null))
        ->assertDontSee('example-password')
        ->assertDontSee('example-key-9f3a')
        ->assertDontSee('example-login@example.com');
});

test('a DataForSEO login is always proven against the service and stored encrypted', function () {
    Http::fake([DATAFORSEO_ACCOUNT_URL => Http::response(['status_code' => 20000])]);
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->put(route('connections.search'), ['login' => 'example-login', 'password' => 'example-password'])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Web search connected. DataForSEO accepted the login.');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('example-login:example-password')));
    expect($label->refresh()->dataforseo_password)->toBe('example-password')
        ->and(DB::table('labels')->where('id', $label->id)->value('dataforseo_password'))->not->toContain('example-password');
});

test('a DataForSEO login that is refused or incomplete is not stored', function (array $input, string $field) {
    Http::fake([DATAFORSEO_ACCOUNT_URL => Http::response(['status_code' => 40100], 401)]);
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))->put(route('connections.search'), $input)->assertSessionHasErrors($field);

    expect($label->refresh()->dataforseo_login)->toBeNull();
})->with([
    'refused by the service' => [['login' => 'example-login', 'password' => 'wrong'], 'login'],
    'no password' => [['login' => 'example-login', 'password' => ''], 'password'],
    'no login' => [['login' => '', 'password' => 'example-password'], 'login'],
]);

test('disconnecting the web search deletes the stored login', function () {
    $label = Label::factory()->create(['dataforseo_login' => 'example-login', 'dataforseo_password' => 'example-password']);

    $this->actingAs(labelAdmin($label))->delete(route('connections.search.destroy'))->assertSessionHasNoErrors();

    expect($label->refresh()->only(['dataforseo_login', 'dataforseo_password']))->toBe(['dataforseo_login' => null, 'dataforseo_password' => null]);
});

test('a research uses the login of its label', function () {
    Http::fake(['https://api.dataforseo.com/v3/serp/*' => Http::response(['status_code' => 20000, 'tasks' => [['status_code' => 20000, 'result' => [['items' => []]]]]])]);
    $label = Label::factory()->create(['dataforseo_login' => 'label-login', 'dataforseo_password' => 'label-password']);

    app(WebSearch::class)->for($label)->search('"Example Artist" "Example Track"', 'DE');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('label-login:label-password')));
});

test('connecting an AI service has the chosen model read a sample receipt and keeps how it did', function () {
    Http::fake([INFOMANIAK_MODELS_URL => Http::response(['data' => [['id' => 'whisper']]])]);
    ReceiptReader::fake([sampleReading()]);
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->put(route('connections.ai'), ['provider' => 'infomaniak', 'key' => 'example-key', 'endpoint' => '12345', 'model' => 'example/small-vision'])
        ->assertSessionHasNoErrors();

    ReceiptReader::assertPrompted(fn ($prompt): bool => $prompt->attachments->count() === 1);
    $label->refresh();

    expect($label->only(['ai_provider', 'ai_key', 'ai_endpoint', 'ai_model']))
        ->toBe(['ai_provider' => 'infomaniak', 'ai_key' => 'example-key', 'ai_endpoint' => '12345', 'ai_model' => 'example/small-vision'])
        ->and($label->ai_trial['missed'])->toBe([])
        ->and($label->ai_trial)->toHaveKeys(['seconds', 'at'])
        ->and(DB::table('labels')->where('id', $label->id)->value('ai_key'))->not->toContain('example-key');
});

test('a model that misreads the sample receipt is connected and what it got wrong is kept', function () {
    Http::fake([INFOMANIAK_MODELS_URL => Http::response(['data' => []])]);
    ReceiptReader::fake([sampleReading(['total' => '41.93', 'date' => '14.03.2026'])]);
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->put(route('connections.ai'), ['provider' => 'infomaniak', 'key' => 'example-key', 'endpoint' => '12345', 'model' => 'example/small-vision'])
        ->assertSessionHasNoErrors();

    expect($label->refresh()->ai_trial['missed'])->toBe(['date', 'total']);
});

test('an AI connection that cannot work is not stored', function (array $input, array $models, Closure $reader, string $field, string $message) {
    Http::fake([INFOMANIAK_MODELS_URL => Http::response(...$models)]);
    $reader();
    $label = Label::factory()->create();

    $response = $this->actingAs(labelAdmin($label))
        ->put(route('connections.ai'), ['provider' => 'infomaniak', 'key' => 'example-key', 'endpoint' => '12345', 'model' => 'example/small-vision', ...$input]);

    $response->assertSessionHasErrors($field);
    expect(session('errors')->first($field))->toContain($message)
        ->and($label->refresh()->ai_provider)->toBeNull();
})->with([
    'a key the service refuses' => [[], [['error' => 'unauthorized'], 401], fn () => ReceiptReader::fake([sampleReading()]), 'key', 'The AI service does not accept this key and product ID.'],
    'a model that cannot read the sample' => [[], [['data' => [['id' => 'whisper']]]], fn () => ReceiptReader::fake(fn () => throw new RuntimeException('This model does not support image input.')), 'model', 'This model could not read the sample receipt: This model does not support image input. Models the service lists: whisper'],
    'no service' => [['provider' => ''], [['data' => []]], fn () => ReceiptReader::fake([sampleReading()]), 'provider', 'required'],
    'no key' => [['key' => ''], [['data' => []]], fn () => ReceiptReader::fake([sampleReading()]), 'key', 'Enter the API key of this service.'],
    'no product id' => [['endpoint' => ''], [['data' => []]], fn () => ReceiptReader::fake([sampleReading()]), 'endpoint', 'This service needs its Product ID.'],
    'no model' => [['model' => ''], [['data' => []]], fn () => ReceiptReader::fake([sampleReading()]), 'model', 'Name the model to read receipts with.'],
]);

test('the model of a connected service is changed without entering the key again, after the new one read the sample receipt', function () {
    ReceiptReader::fake([sampleReading()]);
    $label = Label::factory()->create(['ai_provider' => 'infomaniak', 'ai_key' => 'example-key', 'ai_endpoint' => '12345', 'ai_model' => 'example/small-vision']);

    $this->actingAs(labelAdmin($label))
        ->put(route('connections.ai.model'), ['model' => 'example/large-vision'])
        ->assertSessionHasNoErrors();

    ReceiptReader::assertPrompted(fn ($prompt): bool => $prompt->attachments->count() === 1);
    $label->refresh();

    expect($label->only(['ai_provider', 'ai_key', 'ai_endpoint', 'ai_model']))
        ->toBe(['ai_provider' => 'infomaniak', 'ai_key' => 'example-key', 'ai_endpoint' => '12345', 'ai_model' => 'example/large-vision'])
        ->and($label->ai_trial['missed'])->toBe([]);
});

test('a model that cannot read the sample receipt does not replace the one in use', function () {
    ReceiptReader::fake(fn () => throw new RuntimeException('This model does not support image input.'));
    $label = Label::factory()->create(['ai_provider' => 'infomaniak', 'ai_key' => 'example-key', 'ai_endpoint' => '12345', 'ai_model' => 'example/small-vision']);

    $this->actingAs(labelAdmin($label))
        ->put(route('connections.ai.model'), ['model' => 'example/text-only'])
        ->assertSessionHasErrors(['model' => 'This model could not read the sample receipt: This model does not support image input.']);

    expect($label->refresh()->ai_model)->toBe('example/small-vision');
});

test('there is no model to change while no service is connected', function () {
    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->put(route('connections.ai.model'), ['model' => 'example/large-vision'])
        ->assertNotFound();
});

test('disconnecting the AI service deletes the stored key and what belongs to it', function () {
    $label = Label::factory()->create(['ai_provider' => 'infomaniak', 'ai_key' => 'example-key', 'ai_endpoint' => '12345', 'ai_model' => 'example/small-vision', 'ai_trial' => ['seconds' => 3.2, 'missed' => [], 'at' => '2026-11-19T12:00:00+00:00']]);

    $this->actingAs(labelAdmin($label))->delete(route('connections.ai.destroy'))->assertSessionHasNoErrors();

    expect($label->refresh()->only(['ai_provider', 'ai_key', 'ai_endpoint', 'ai_model', 'ai_trial']))
        ->toBe(['ai_provider' => null, 'ai_key' => null, 'ai_endpoint' => null, 'ai_model' => null, 'ai_trial' => null]);
});

test('the sample receipt is a readable picture and is scored field by field', function () {
    $picture = SampleReceipt::picture();
    [$width, $height] = getimagesize($picture);
    $image = imagecreatefrompng($picture);
    // Nothing is drawn into the right-hand margin: a line that ran over the edge would be.
    $margin = array_map(fn (int $y): int => imagecolorat($image, $width - 6, $y) & 0xFFFFFF, range(0, $height - 1, 2));
    unlink($picture);

    expect($width)->toBeGreaterThan(900)->and($height)->toBeGreaterThan(500)
        ->and(array_unique($margin))->toBe([0xFFFFFF])
        ->and(SampleReceipt::missed(sampleReading()))->toBe([])
        ->and(SampleReceipt::missed(sampleReading(['vendor' => 'EXAMPLE HOSTING GMBH, Berlin', 'total' => '49.9', 'currency' => 'eur'])))->toBe([])
        ->and(SampleReceipt::missed(['vendor' => 'Another Company', 'total' => '41.93']))->toBe(['vendor', 'date', 'total', 'currency']);
});

test('only an admin of the installation sees and changes the file storage', function () {
    $label = Label::factory()->create();
    $admin = labelAdmin($label);

    $this->actingAs($admin)->put(route('connections.storage'), ['disk' => 'local'])->assertForbidden();
    $this->actingAs($admin)->delete(route('connections.storage.destroy'))->assertForbidden();
    expect(InstallationSettings::storage())->toBeNull();

    $admin->forceFill(['installation_admin' => true])->save();

    $this->actingAs($admin)->get(route('connections.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('storage.disk', 'local')->where('storage.from_environment', true));
});

test('the file storage chosen in the interface takes effect and disconnecting hands it back to the environment', function () {
    $admin = labelAdmin(Label::factory()->create());
    $admin->forceFill(['installation_admin' => true])->save();
    $this->actingAs($admin);
    config(['royalties.storage.disk' => 's3']);

    $this->put(route('connections.storage'), ['disk' => 'local'])->assertSessionHasNoErrors();
    InstallationSettings::apply();

    expect(InstallationSettings::storage())->toBe(['disk' => 'local'])
        ->and(WorkingCopy::disk())->toBe('local')
        ->and(DB::table('installation_settings')->value('value'))->not->toContain('local');

    $this->delete(route('connections.storage.destroy'))->assertSessionHasNoErrors();

    expect(InstallationSettings::storage())->toBeNull();
});

test('a bucket needs a name and keys before anything is stored', function (array $input, string $field) {
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
    $this->actingAs($payee)->put(route('connections.ai'), ['provider' => 'infomaniak'])->assertForbidden();
    $this->actingAs($payee)->delete(route('connections.ai.destroy'))->assertForbidden();
});

test('the first account created looks after the installation and later ones do not', function () {
    $this->artisan('royalties:create-user', ['email' => 'first@example.com'])->assertSuccessful();
    $this->artisan('royalties:create-user', ['email' => 'second@example.com'])->assertSuccessful();

    expect(User::query()->where('email', 'first@example.com')->sole()->installation_admin)->toBeTrue()
        ->and(User::query()->where('email', 'second@example.com')->sole()->installation_admin)->toBeFalse();
});

test('only who runs the installation may point the AI connection at the server or its network', function (string $endpoint, array $addresses) {
    PublicAddress::resolveUsing(fn (string $host): array => $addresses);
    $label = Label::factory()->create();
    $input = ['provider' => 'openai-compatible', 'key' => 'example-key', 'endpoint' => $endpoint, 'model' => 'example/vision'];

    $this->actingAs(labelAdmin($label))->put(route('connections.ai'), $input)->assertSessionHasErrors('endpoint');

    Http::assertNothingSent();
    expect($label->refresh()->ai_endpoint)->toBeNull();
})->with([
    'the server itself' => ['https://127.0.0.1:6379', []],
    'a cloud metadata service' => ['https://169.254.169.254/latest?', []],
    'a name that leads into the private network' => ['https://models.example.com/v1', ['10.0.0.5']],
    'plain http' => ['http://models.example.com/v1', ['203.0.114.10']],
    'a name that cannot be looked up' => ['https://models.example.com/v1', []],
]);

test('the connections page lists every service with its switch and its options', function () {
    $label = Label::factory()->create([
        'airplay_markets' => ['FR'],
        'importer_settings' => ['bandcamp' => ['disable_fee' => true]],
        'delivery_settings' => ['distributors' => ['subvert' => false], 'zebralution' => ['number' => '1100a1']],
    ]);

    $this->actingAs(labelAdmin($label))->get(route('connections.edit'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('services', fn ($services): bool => collect($services)->pluck('key')->all() === ['zebralution', 'bandcamp', 'subvert', 'rightz-audio']
                && collect($services)->pluck('enabled')->all() === [true, true, false, true])
            ->where('services.0.kind', 'Distributor')
            ->where('zebralution.number', '1100a1')->where('zebralution.price_group', 20)
            ->where('zebralution.work_rights_options.1', 'licensed to distributor by label')
            ->where('bandcamp.disable_fee', true)->where('bandcamp.exclude_physical', false)
            ->where('search.airplay_markets', ['FR'])
            ->where('label.services', ['zebralution', 'bandcamp', 'rightz-audio']));
});

test('the Zebralution card stores its switch and its delivery fields and no other card changes', function () {
    $label = Label::factory()->create([
        'airplay_markets' => ['FR'],
        'importer_settings' => ['bandcamp' => ['disable_fee' => true, 'exclude_physical' => true]],
        'delivery_settings' => ['distributors' => ['bandcamp' => false]],
    ]);
    $this->actingAs(labelAdmin($label));

    $this->put(route('connections.service', 'zebralution'), [
        'enabled' => '1', 'number' => '1100a1', 'origin_country' => 'de', 'price_group' => '30', 'work_rights' => '',
    ])->assertSessionHasNoErrors()->assertInertiaFlash('toast.message', 'Zebralution saved.');

    $stored = ['number' => '1100a1', 'origin_country' => 'DE', 'price_group' => 30, 'work_rights' => 'administered by collecting society'];
    $label->refresh();

    expect($label->delivery_settings)->toBe(['distributors' => ['bandcamp' => false, 'zebralution' => true], 'zebralution' => $stored])
        ->and($label->importer_settings)->toBe(['bandcamp' => ['disable_fee' => true, 'exclude_physical' => true]])
        ->and($label->airplay_markets)->toBe(['FR']);

    // A card that sends only its switch leaves the fields alone.
    $this->put(route('connections.service', 'zebralution'), ['enabled' => '0'])->assertSessionHasNoErrors();

    expect($label->refresh()->delivery_settings)->toBe(['distributors' => ['bandcamp' => false, 'zebralution' => false], 'zebralution' => $stored]);
});

test('the Bandcamp card stores its options and its switch and leaves Zebralution alone', function () {
    $label = Label::factory()->create(['delivery_settings' => ['zebralution' => ['number' => '1100a1']]]);
    $this->actingAs(labelAdmin($label));

    $this->put(route('connections.service', 'bandcamp'), ['enabled' => '1', 'disable_fee' => '1', 'exclude_physical' => '0'])->assertSessionHasNoErrors();

    expect($label->refresh()->importer_settings)->toBe(['bandcamp' => ['disable_fee' => true, 'exclude_physical' => false]])
        ->and($label->delivery_settings)->toBe(['zebralution' => ['number' => '1100a1'], 'distributors' => ['bandcamp' => true]]);

    // Switching an option off again, and a card without the options leaves them alone.
    $this->put(route('connections.service', 'bandcamp'), ['disable_fee' => '0', 'exclude_physical' => '1'])->assertSessionHasNoErrors();
    $this->put(route('connections.service', 'bandcamp'), ['enabled' => '0'])->assertSessionHasNoErrors();

    expect($label->refresh()->importer_settings)->toBe(['bandcamp' => ['disable_fee' => false, 'exclude_physical' => true]])
        ->and($label->usesService('bandcamp'))->toBeFalse();
});

test('a service card refuses what is not its own and a service this installation does not know', function (string $service, array $input, string $error) {
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))->put(route('connections.service', $service), $input)->assertSessionHasErrors($error);

    expect($label->refresh()->delivery_settings)->toBeNull()->and($label->importer_settings)->toBeNull();
})->with([
    'a switch that is no boolean' => ['subvert', ['enabled' => 'maybe'], 'enabled'],
    'a Bandcamp option that is no boolean' => ['bandcamp', ['disable_fee' => 'maybe'], 'disable_fee'],
    'a Zebralution price group that does not exist' => ['zebralution', ['number' => '1', 'price_group' => 15], 'price_group'],
    'Zebralution rights in other words' => ['zebralution', ['number' => '1', 'work_rights' => 'ours'], 'work_rights'],
    'a country of origin that is no code' => ['zebralution', ['number' => '1', 'origin_country' => 'Germany'], 'origin_country'],
]);

test('a service this installation does not know has no card', function () {
    $this->actingAs(labelAdmin(Label::factory()->create()))->put('/settings/connections/services/example', ['enabled' => '1'])->assertNotFound();
});

test('the airplay research countries are stored split, upper-cased and deduplicated, and only they change', function () {
    $label = Label::factory()->create(['delivery_settings' => ['distributors' => ['zebralution' => false]]]);

    $this->actingAs(labelAdmin($label))
        ->put(route('connections.search.markets'), ['airplay_markets' => 'de, gb us DE'])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Airplay research countries saved.');

    expect($label->refresh()->airplay_markets)->toBe(['DE', 'GB', 'US'])
        ->and($label->delivery_settings)->toBe(['distributors' => ['zebralution' => false]]);
});

test('airplay research countries that are no country codes are rejected', function (string $value, string $error) {
    $label = Label::factory()->create(['airplay_markets' => ['FR']]);

    $this->actingAs(labelAdmin($label))
        ->put(route('connections.search.markets'), ['airplay_markets' => $value])
        ->assertSessionHasErrors($error);

    expect($label->refresh()->airplay_markets)->toBe(['FR']);
})->with([
    'a country name' => ['Germany', 'airplay_markets.0'],
    'nothing' => ['', 'airplay_markets'],
]);

test('only a label admin can change a service', function () {
    $label = Label::factory()->create();
    $payee = User::factory()->create(['current_label_id' => $label->id]);
    $label->users()->attach($payee, ['role' => LabelRole::Payee->value]);

    $this->actingAs($payee)->put(route('connections.service', 'bandcamp'), ['enabled' => '0'])->assertForbidden();
    $this->actingAs($payee)->put(route('connections.search.markets'), ['airplay_markets' => 'de'])->assertForbidden();

    expect($label->refresh()->delivery_settings)->toBeNull();
});
