<?php

use App\Models\DataImport;
use App\Models\Label;
use App\Models\Receipt;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\User;
use App\Support\DirectUploads;
use Aws\MockHandler;
use Aws\Result;
use Illuminate\Support\Facades\Storage;

/**
 * Files are stored in a bucket: the test double of it, so nothing leaves this machine.
 */
function storeInBucket(): void
{
    config(['royalties.storage.disk' => 's3', 'filesystems.disks.s3.driver' => 's3']);
}

/**
 * A bucket client that signs for real but can reach nothing: every call is answered from $answers.
 */
function unreachableBucket(Result ...$answers): MockHandler
{
    $handler = new MockHandler($answers);

    config(['royalties.storage.disk' => 's3', 'filesystems.disks.s3' => [
        'driver' => 's3', 'key' => 'example', 'secret' => 'example', 'region' => 'us-east-1', 'bucket' => 'example-bucket',
        'endpoint' => 'https://storage.invalid', 'use_path_style_endpoint' => true, 'handler' => $handler,
    ]]);
    Storage::forgetDisk('s3');

    return $handler;
}

test('without a bucket the browser is told to post files to the server', function () {
    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->postJson(route('uploads.sign'), ['name' => 'Example Master.wav'])
        ->assertOk()
        ->assertExactJson(['url' => null]);
});

test('with a bucket the browser gets a signed address in the upload area of its own label', function () {
    unreachableBucket(new Result(['CORSRules' => [['AllowedOrigins' => ['http://localhost'], 'AllowedMethods' => ['PUT']]]]));
    $label = Label::factory()->create();

    $target = $this->actingAs(labelAdmin($label))->postJson(route('uploads.sign'), ['name' => 'Example Master.WAV'])->assertOk()->json();

    expect($target['key'])->toMatch('/^uploads\/'.$label->id.'\/[0-9a-f-]{36}\.wav$/')
        ->and($target['url'])->toContain('https://storage.invalid/example-bucket/'.$target['key'])->toContain('X-Amz-Signature=')
        ->and(array_change_key_case($target['headers']))->not->toHaveKey('host');
});

test('a bucket that does not take files from this address is not tried', function () {
    unreachableBucket(new Result(['CORSRules' => [['AllowedOrigins' => ['https://other.example'], 'AllowedMethods' => ['PUT']]]]));

    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->postJson(route('uploads.sign'), ['name' => 'Example Master.wav'])
        ->assertOk()
        ->assertExactJson(['url' => null]);
});

test('a payee cannot ask for an upload address', function () {
    $label = Label::factory()->create();
    $user = User::factory()->create(['current_label_id' => $label->id]);
    $label->users()->attach($user, ['role' => 'payee']);

    $this->actingAs($user)->postJson(route('uploads.sign'), ['name' => 'example.pdf'])->assertForbidden();
});

test('a release file sent to the bucket is moved to the release and its type is read off the file', function () {
    storeInBucket();
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id]);
    $key = "uploads/{$label->id}/0b0b8a52-6f0c-4d0e-9f0e-3a1f0c9d2e11.pdf";
    Storage::disk('s3')->put($key, "%PDF-1.4\nExample press kit");

    $this->actingAs(labelAdmin($label))
        ->post(route('release-files.store', $release->id), ['upload' => $key, 'upload_name' => 'Press Kit.pdf'])
        ->assertSessionHasNoErrors();

    $file = ReleaseFile::query()->withoutGlobalScopes()->sole();

    expect($file->original_name)->toBe('Press Kit.pdf')
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->size)->toBe(26)
        ->and($file->path)->toStartWith("releases/{$label->id}/{$release->id}/");
    Storage::disk('s3')->assertExists($file->path);
    Storage::disk('s3')->assertMissing($key);
});

test('a file in the upload area of another label, or anywhere else in the bucket, cannot be claimed', function (string $key) {
    storeInBucket();
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id]);
    Storage::disk('s3')->put($key, 'example');

    $this->actingAs(labelAdmin($label))
        ->post(route('release-files.store', $release->id), ['upload' => $key, 'upload_name' => 'example.wav'])
        ->assertSessionHasErrors('file');

    expect(ReleaseFile::query()->withoutGlobalScopes()->count())->toBe(0);
    Storage::disk('s3')->assertExists($key);
})->with([
    'another label' => ['uploads/999999/0b0b8a52-6f0c-4d0e-9f0e-3a1f0c9d2e11.wav'],
    'a stored master' => ['releases/1/1/0b0b8a52-6f0c-4d0e-9f0e-3a1f0c9d2e11.wav'],
]);

test('a receipt sent to the bucket that is not a receipt file is refused and removed', function () {
    storeInBucket();
    $label = Label::factory()->create();
    $key = "uploads/{$label->id}/0b0b8a52-6f0c-4d0e-9f0e-3a1f0c9d2e11.pdf";
    Storage::disk('s3')->put($key, 'plain text that only claims to be a PDF');

    $this->actingAs(labelAdmin($label))
        ->post(route('receipts.store'), ['upload' => $key, 'upload_name' => 'receipt.pdf'])
        ->assertSessionHasErrors(['file' => 'A receipt has to be a PDF, JPEG, PNG or WebP file.']);

    expect(Receipt::query()->withoutGlobalScopes()->count())->toBe(0);
    Storage::disk('s3')->assertMissing($key);
});

test('a statement sent to the bucket is imported from there', function () {
    storeInBucket();
    $label = Label::factory()->create(['currency' => 'EUR']);
    $key = "uploads/{$label->id}/0b0b8a52-6f0c-4d0e-9f0e-3a1f0c9d2e11.csv";
    Storage::disk('s3')->put($key, "date,total,release name\n2026-03-31,12.50,Example Release\n");

    $this->actingAs(labelAdmin($label))
        ->post(route('data-imports.store'), ['importer' => 'template', 'upload' => $key, 'upload_name' => 'Example 2026 Q1.csv'])
        ->assertSessionHasNoErrors();

    $import = DataImport::query()->withoutGlobalScopes()->sole();

    expect($import->transactions_count)->toBe(1)
        ->and($import->original_filename)->toBe('Example 2026 Q1.csv')
        ->and($import->file_path)->toStartWith("imports/{$label->id}/")->toEndWith('.csv');
    Storage::disk('s3')->assertExists($import->file_path);
    Storage::disk('s3')->assertMissing($key);
});

test('uploads nobody reported are removed after a day', function () {
    storeInBucket();
    Storage::disk('s3')->put('uploads/1/old.wav', 'example');
    Storage::disk('s3')->put('uploads/1/new.wav', 'example');
    Storage::disk('s3')->put('releases/1/1/master.wav', 'example');
    touch(Storage::disk('s3')->path('uploads/1/old.wav'), now()->subDays(2)->getTimestamp());
    touch(Storage::disk('s3')->path('releases/1/1/master.wav'), now()->subDays(2)->getTimestamp());

    expect(DirectUploads::prune())->toBe(1);
    Storage::disk('s3')->assertMissing('uploads/1/old.wav');
    Storage::disk('s3')->assertExists('uploads/1/new.wav');
    Storage::disk('s3')->assertExists('releases/1/1/master.wav');
});

test('allowing direct uploads adds this address to the rules the bucket already has', function () {
    $existing = ['AllowedOrigins' => ['https://other.example'], 'AllowedMethods' => ['PUT']];
    $bucket = unreachableBucket(new Result(['CORSRules' => [$existing]]), new Result([]));
    $admin = labelAdmin(Label::factory()->create());
    $admin->forceFill(['installation_admin' => true])->save();

    $this->actingAs($admin)->post('https://royalties.example/settings/connections/storage/direct-uploads')->assertSessionHasNoErrors();

    $sent = $bucket->getLastCommand();

    expect($sent->getName())->toBe('PutBucketCors')
        ->and($sent['CORSConfiguration']['CORSRules'][0])->toBe($existing)
        ->and($sent['CORSConfiguration']['CORSRules'][1]['AllowedOrigins'])->toBe(['https://royalties.example'])
        ->and($sent['CORSConfiguration']['CORSRules'][1]['AllowedMethods'])->toBe(['PUT']);
});

test('only the admin of the installation changes what the bucket accepts', function () {
    $bucket = unreachableBucket();

    $this->actingAs(labelAdmin(Label::factory()->create()))->post(route('connections.storage.direct-uploads'))->assertForbidden();

    expect($bucket->count())->toBe(0)->and($bucket->getLastCommand())->toBeNull();
});
