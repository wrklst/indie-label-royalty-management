<?php

use App\Enums\AudioPreviewStatus;
use App\Enums\ReleaseFileKind;
use App\Models\DataImport;
use App\Models\Label;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\Transaction;
use App\Services\Imports\ImportRunner;
use App\Services\Releases\ReleaseImageSize;
use App\Services\Releases\ReleaseImageVariants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
    config(['filesystems.disks.s3.driver' => 's3']);
});

test('an upload goes to the configured disk and the file remembers it', function () {
    config(['royalties.storage.disk' => 's3']);
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id]);

    $this->actingAs(labelAdmin($label))
        ->post(route('release-files.store', $release->id), ['file' => UploadedFile::fake()->create('notes.txt', 4, 'text/plain')])
        ->assertSessionHasNoErrors();

    $file = ReleaseFile::query()->withoutGlobalScopes()->sole();

    expect($file->disk)->toBe('s3');
    Storage::disk('s3')->assertExists($file->path);
    Storage::disk('local')->assertMissing($file->path);
});

test('a file in a bucket is served by a short-lived link instead of through the server', function () {
    $label = Label::factory()->create();
    $file = ReleaseFile::factory()->create(['label_id' => $label->id, 'release_id' => Release::factory()->create(['label_id' => $label->id]), 'disk' => 's3', 'path' => 'releases/example.pdf', 'mime_type' => 'application/pdf', 'original_name' => 'Example.pdf']);
    Storage::disk('s3')->put('releases/example.pdf', 'example');

    $response = $this->actingAs(labelAdmin($label))->get(route('release-files.show', $file->id));

    $response->assertRedirect();

    expect($response->headers->get('Location'))->toContain('releases/example.pdf')->toContain('expiration=')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('a file in the bucket of another label is not handed out', function () {
    $file = ReleaseFile::factory()->create(['disk' => 's3', 'path' => 'releases/example.pdf']);
    Storage::disk('s3')->put('releases/example.pdf', 'example');

    $this->actingAs(labelAdmin(Label::factory()->create()))->get(route('release-files.show', $file->id))->assertNotFound();
});

test('a smaller size of an image in a bucket is made from a download and stored back in the bucket', function () {
    $file = ReleaseFile::factory()->create(['disk' => 's3', 'path' => 'releases/cover.jpg', 'mime_type' => 'image/jpeg', 'kind' => ReleaseFileKind::Cover]);
    Storage::disk('s3')->put('releases/cover.jpg', UploadedFile::fake()->image('cover.jpg', 1200, 1200)->getContent());

    $variant = app(ReleaseImageVariants::class)->path($file, ReleaseImageSize::Thumb);

    expect($variant)->not->toBeNull();
    Storage::disk('s3')->assertExists($variant);
    expect(getimagesizefromstring(Storage::disk('s3')->get($variant))[0])->toBeLessThan(1200)
        ->and(app(ReleaseImageVariants::class)->path($file, ReleaseImageSize::Thumb))->toBe($variant);
});

test('a statement stored in a bucket is imported, and one uploaded before the move is still found on the server', function (string $storedOn) {
    config(['royalties.storage.disk' => 's3']);
    $label = currentLabel(['currency' => 'EUR']);
    Storage::disk($storedOn)->put('imports/example.csv', "date,total,release name\n2026-03-31,12.50,Example Release\n");

    $import = app(ImportRunner::class)->run(DataImport::factory()->create([
        'label_id' => $label->id, 'importer' => 'template', 'status' => 'pending', 'file_path' => 'imports/example.csv',
    ]), $label);

    expect($import->transactions_count)->toBe(1)
        ->and(Transaction::query()->sole()->total)->toBe('12.500000000000000');
})->with(['s3', 'local']);

test('moving copies release files, playback copies and statements to the bucket and keeps the originals', function () {
    $file = ReleaseFile::factory()->create([
        'disk' => 'local', 'path' => 'releases/1/master.wav', 'preview_path' => 'previews/1/master.mp3', 'preview_status' => AudioPreviewStatus::Ready,
    ]);
    $gone = ReleaseFile::factory()->create(['disk' => 'local', 'path' => 'releases/1/gone.wav']);
    DataImport::factory()->create(['file_path' => 'imports/1/statement.csv']);
    Storage::disk('local')->put('releases/1/master.wav', 'master');
    Storage::disk('local')->put('previews/1/master.mp3', 'preview');
    Storage::disk('local')->put('imports/1/statement.csv', 'statement');

    $this->artisan('royalties:move-files', ['--to' => 's3'])
        ->expectsOutputToContain('Copied 1 release file and 1 statement file to [s3]. Missing at the source: 1. Failed: 0.')
        ->assertSuccessful();

    expect($file->refresh()->disk)->toBe('s3')
        ->and($gone->refresh()->disk)->toBe('local')
        ->and(Storage::disk('s3')->get('releases/1/master.wav'))->toBe('master')
        ->and(Storage::disk('s3')->get('previews/1/master.mp3'))->toBe('preview')
        ->and(Storage::disk('s3')->get('imports/1/statement.csv'))->toBe('statement');
    Storage::disk('local')->assertExists('releases/1/master.wav');
});

test('a dry run of a move changes nothing', function () {
    $file = ReleaseFile::factory()->create(['disk' => 'local', 'path' => 'releases/1/master.wav']);
    Storage::disk('local')->put('releases/1/master.wav', 'master');

    $this->artisan('royalties:move-files', ['--to' => 's3', '--dry-run' => true])
        ->expectsOutputToContain('Would copy 1 release file')
        ->assertSuccessful();

    expect($file->refresh()->disk)->toBe('local');
    Storage::disk('s3')->assertMissing('releases/1/master.wav');
});

test('a move needs a disk that exists', function () {
    $this->artisan('royalties:move-files', ['--to' => 'nowhere'])->assertFailed();
});
