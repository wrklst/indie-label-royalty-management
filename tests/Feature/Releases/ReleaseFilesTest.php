<?php

use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\Payee;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\Track;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    $this->label = Label::factory()->create();
    $this->release = Release::factory()->create(['label_id' => $this->label->id, 'name' => 'Example Release']);
    $this->actingAs(labelAdmin($this->label));
});

function storedReleaseFile(Release $release, array $attributes = [], string $contents = 'file contents'): ReleaseFile
{
    $file = ReleaseFile::factory()->create(['label_id' => $release->label_id, 'release_id' => $release->id, ...$attributes]);
    Storage::disk('local')->put($file->path, $contents);

    return $file;
}

test('an uploaded file is kept on the private disk under an unguessable name', function () {
    $this->post(route('release-files.store', $this->release), ['file' => UploadedFile::fake()->create('Press kit.pdf', 20, 'application/pdf')])
        ->assertSessionHasNoErrors();

    $file = ReleaseFile::query()->withoutGlobalScopes()->sole();

    expect($file->original_name)->toBe('Press kit.pdf')
        ->and($file->kind->value)->toBe('document')
        ->and($file->path)->toStartWith("releases/{$this->label->id}/{$this->release->id}/")
        ->and($file->path)->not->toContain('Press')
        ->and($file->checksum)->toHaveLength(64);

    Storage::disk('local')->assertExists($file->path);
    Storage::disk('public')->assertMissing($file->path);
});

test('audio is attached to the track its file name points at', function (string $fileName, ?string $expectedTrack) {
    Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $this->release->id, 'name' => 'Opening', 'track_number' => 1, 'isrc' => 'ZZTST2600001']);
    Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $this->release->id, 'name' => 'Closing Time', 'track_number' => 2, 'isrc' => 'ZZTST2600002']);

    $this->post(route('release-files.store', $this->release), ['file' => UploadedFile::fake()->create($fileName, 50, 'audio/wav')])
        ->assertSessionHasNoErrors();

    $file = ReleaseFile::query()->withoutGlobalScopes()->sole();

    expect($file->kind->value)->toBe('audio')
        ->and($file->track?->name)->toBe($expectedTrack);
})->with([
    'a leading track number' => ['02 Something.wav', 'Closing Time'],
    'the title' => ['Example Artist - closing time (master).wav', 'Closing Time'],
    'the isrc' => ['ZZ-TST-26-00001.wav', 'Opening'],
    'nothing recognisable' => ['bounce final v3.wav', null],
]);

test('a new cover replaces the old one, which is kept as artwork', function () {
    $old = storedReleaseFile($this->release, ['kind' => 'cover', 'mime_type' => 'image/jpeg', 'original_name' => 'old.jpg']);

    $this->post(route('release-files.store', $this->release), ['file' => UploadedFile::fake()->image('new.jpg', 600, 600), 'kind' => 'cover'])
        ->assertSessionHasNoErrors();

    expect($old->refresh()->kind->value)->toBe('artwork')
        ->and($this->release->cover()->first()?->original_name)->toBe('new.jpg');
});

test('a cover must be an image', function () {
    $this->post(route('release-files.store', $this->release), ['file' => UploadedFile::fake()->create('cover.pdf', 10, 'application/pdf'), 'kind' => 'cover'])
        ->assertSessionHasErrors(['file' => 'A cover must be a JPEG, PNG or WebP image.']);

    $this->assertDatabaseCount('release_files', 0);
});

test('files that could run in a browser or on a server are refused', function (string $name) {
    $this->post(route('release-files.store', $this->release), ['file' => UploadedFile::fake()->create($name, 1)])
        ->assertSessionHasErrors('file');

    $this->assertDatabaseCount('release_files', 0);
})->with(['shell.php', 'page.html', 'logo.svg']);

test('a file cannot be attached to a track of another release', function () {
    $foreign = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => Release::factory()->create(['label_id' => $this->label->id])]);

    $this->post(route('release-files.store', $this->release), ['file' => UploadedFile::fake()->create('a.wav', 5, 'audio/wav'), 'track_id' => $foreign->id])
        ->assertSessionHasErrors('track_id');
});

test('audio is streamed in place and supports seeking', function () {
    $file = storedReleaseFile($this->release, ['kind' => 'audio', 'mime_type' => 'audio/mpeg', 'original_name' => 'track.mp3', 'path' => 'releases/a/track.mp3'], '0123456789');

    $response = $this->get(route('release-files.show', $file->id), ['Range' => 'bytes=2-5']);

    $response->assertStatus(206)
        ->assertHeader('Content-Type', 'audio/mpeg')
        ->assertHeader('Content-Range', 'bytes 2-5/10')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
});

test('a file is sent as a download on request or when it is not safe to display', function () {
    $pdf = storedReleaseFile($this->release, ['path' => 'releases/a/kit.pdf']);
    $other = storedReleaseFile($this->release, ['mime_type' => 'text/html', 'original_name' => 'notes.txt', 'path' => 'releases/a/notes.txt']);

    expect($this->get(route('release-files.show', [$pdf->id, 'download' => 1]))->headers->get('Content-Disposition'))->toStartWith('attachment');

    $response = $this->get(route('release-files.show', $other->id));

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($response->headers->get('Content-Type'))->toBe('application/octet-stream');
});

test('files of a release are out of reach for other labels, payees and guests', function () {
    $file = storedReleaseFile($this->release);

    $this->actingAs(labelAdmin(Label::factory()->create()))->get(route('release-files.show', $file->id))->assertNotFound();
    $this->delete(route('release-files.destroy', $file->id))->assertNotFound();

    $payeeUser = User::factory()->create(['current_label_id' => $this->label->id]);
    $this->label->users()->attach($payeeUser, ['role' => LabelRole::Payee->value, 'payee_id' => Payee::factory()->create(['label_id' => $this->label->id])->id]);
    $this->actingAs($payeeUser)->get(route('release-files.show', $file->id))->assertRedirect(route('portal.index'));

    auth()->logout();
    $this->get(route('release-files.show', $file->id))->assertRedirect(route('login'));

    Storage::disk('local')->assertExists($file->path);
});

test('the stored path is never exposed and cannot be fetched directly', function () {
    $file = storedReleaseFile($this->release);

    $this->get(route('releases.show', $this->release))
        ->assertInertia(fn ($page) => $page->has('release.files', 1)->missing('release.files.0.path')->missing('release.files.0.disk'));

    // The framework's own file route only answers requests carrying a signature.
    $this->get('/storage/'.$file->path)->assertForbidden();
});

test('a file can be turned into the cover or moved to a track', function () {
    $track = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $this->release->id]);
    $image = storedReleaseFile($this->release, ['kind' => 'artwork', 'mime_type' => 'image/png', 'original_name' => 'front.png']);
    $audio = storedReleaseFile($this->release, ['kind' => 'audio', 'mime_type' => 'audio/wav', 'original_name' => 'a.wav']);
    $document = storedReleaseFile($this->release);

    $this->patch(route('release-files.update', $image->id), ['kind' => 'cover'])->assertSessionHasNoErrors();
    $this->patch(route('release-files.update', $audio->id), ['kind' => 'audio', 'track_id' => $track->id])->assertSessionHasNoErrors();
    $this->patch(route('release-files.update', $document->id), ['kind' => 'cover'])->assertSessionHasErrors('kind');

    $this->assertDatabaseHas('release_files', ['id' => $image->id, 'kind' => 'cover']);
    $this->assertDatabaseHas('release_files', ['id' => $audio->id, 'track_id' => $track->id]);
    $this->assertDatabaseHas('release_files', ['id' => $document->id, 'kind' => 'document']);
});

test('deleting a file or its release removes the stored file', function () {
    $single = storedReleaseFile($this->release, ['path' => 'releases/a/one.pdf']);
    $other = Release::factory()->create(['label_id' => $this->label->id]);
    $withRelease = storedReleaseFile($other, ['path' => 'releases/a/two.pdf']);

    $this->delete(route('release-files.destroy', $single->id))->assertRedirect();
    $this->delete(route('releases.destroy', $other->id))->assertRedirect();

    Storage::disk('local')->assertMissing($single->path);
    Storage::disk('local')->assertMissing($withRelease->path);
    $this->assertDatabaseCount('release_files', 0);
});

test('the release page offers the player a format browsers can play', function () {
    $track = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $this->release->id]);
    storedReleaseFile($this->release, ['kind' => 'audio', 'track_id' => $track->id, 'mime_type' => 'audio/aiff', 'original_name' => 'a.aiff']);
    $mp3 = storedReleaseFile($this->release, ['kind' => 'audio', 'track_id' => $track->id, 'mime_type' => 'audio/mpeg', 'original_name' => 'a.mp3']);

    $this->get(route('releases.show', $this->release))
        ->assertInertia(fn ($page) => $page->where('release.tracks.0.audio_file_id', $mp3->id));
});

test('changing the catalog number of a release leaves its files alone', function () {
    $file = storedReleaseFile($this->release);

    $this->put(route('releases.update', $this->release->id), ['name' => 'Example Release', 'catalog_number' => 'EX099'])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('release_files', ['id' => $file->id]);
    Storage::disk('local')->assertExists($file->path);
});

test('the release list keeps showing the cover after other files are added', function () {
    $cover = storedReleaseFile($this->release, ['kind' => 'cover', 'mime_type' => 'image/jpeg', 'original_name' => 'front.jpg', 'path' => 'releases/a/front.jpg']);
    storedReleaseFile($this->release, ['kind' => 'audio', 'mime_type' => 'audio/wav', 'original_name' => 'a.wav', 'path' => 'releases/a/a.wav']);

    $this->get(route('releases.index'))
        ->assertInertia(fn ($page) => $page->where('releases.data.0.cover_file_id', $cover->id));
});
