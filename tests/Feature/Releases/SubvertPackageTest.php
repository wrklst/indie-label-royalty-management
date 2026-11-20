<?php

use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\Payee;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Storage::fake('local');
    $this->label = Label::factory()->create();
    $this->release = Release::factory()->create(['label_id' => $this->label->id, 'name' => 'Example Release']);
    $this->actingAs(labelAdmin($this->label));
});

function subvertTrack(Release $release, array $attributes = []): Track
{
    return Track::factory()->create(['label_id' => $release->label_id, 'release_id' => $release->id, ...$attributes]);
}

/**
 * Keep a file with the release. $bytes makes a file of that size
 * without writing its content, for the size limits.
 */
function subvertFile(Release $release, string $name, array $attributes = [], ?int $bytes = null): ReleaseFile
{
    $file = ReleaseFile::factory()->create([
        'label_id' => $release->label_id, 'release_id' => $release->id, 'kind' => 'audio', 'mime_type' => 'audio/wav',
        'original_name' => $name, 'path' => "releases/{$release->label_id}/{$release->id}/".fake()->uuid(), ...$attributes,
    ]);
    Storage::disk('local')->put($file->path, "contents of {$name}");

    if ($bytes !== null) {
        $handle = fopen(Storage::disk('local')->path($file->path), 'r+');
        ftruncate($handle, $bytes);
        fclose($handle);
    }

    return $file;
}

/**
 * @return array<string, string> name in the archive => content
 */
function zipContents(TestResponse $response): array
{
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $contents = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $contents[(string) $zip->getNameIndex($index)] = (string) $zip->getFromIndex($index);
    }

    return $contents;
}

test('the Subvert ZIP holds the cover and one audio file per track, flat and in track order', function () {
    $this->release->update(['name' => 'Example: Release?', 'version' => 'Deluxe']);
    $second = subvertTrack($this->release, ['name' => 'Closing/Time', 'track_number' => 2, 'version' => 'Club Mix']);
    $first = subvertTrack($this->release, ['name' => 'Opening', 'track_number' => 1]);
    subvertFile($this->release, 'Front.PNG', ['kind' => 'cover', 'mime_type' => 'image/png']);
    subvertFile($this->release, 'two.FLAC', ['track_id' => $second->id]);
    subvertFile($this->release, 'one.wav', ['track_id' => $first->id]);
    subvertFile($this->release, 'unassigned.wav');
    subvertFile($this->release, 'One Sheet.pdf', ['kind' => 'document', 'mime_type' => 'application/pdf']);

    $response = $this->get(route('releases.subvert-package', $this->release))->assertOk()->assertHeader('Content-Type', 'application/zip');

    expect(zipContents($response))->toBe([
        'cover.png' => 'contents of Front.PNG',
        '01 Opening.wav' => 'contents of one.wav',
        '02 Closing Time (Club Mix).flac' => 'contents of two.FLAC',
    ])
        ->and($response->headers->get('Content-Disposition'))->toContain('filename="Example Release (Deluxe).zip"');
});

test('the files in the Subvert ZIP are stored without compression', function () {
    $track = subvertTrack($this->release, ['name' => 'Opening', 'track_number' => 1]);
    subvertFile($this->release, 'front.jpg', ['kind' => 'cover', 'mime_type' => 'image/jpeg']);
    subvertFile($this->release, 'one.wav', ['track_id' => $track->id], 50_000);

    $response = $this->get(route('releases.subvert-package', $this->release))->assertOk();

    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());

    expect($zip->statName('01 Opening.wav'))->toMatchArray(['size' => 50_000, 'comp_size' => 50_000, 'comp_method' => ZipArchive::CM_STORE]);
});

test('each track is sent in its best format, and in the newest file of that', function () {
    $track = subvertTrack($this->release, ['name' => 'Opening', 'track_number' => 1]);
    $other = subvertTrack($this->release, ['name' => 'Closing', 'track_number' => 2]);
    subvertFile($this->release, 'front.jpg', ['kind' => 'cover', 'mime_type' => 'image/jpeg']);
    subvertFile($this->release, 'old master.wav', ['track_id' => $track->id, 'created_at' => '2026-01-01 10:00:00']);
    subvertFile($this->release, 'new master.aiff', ['track_id' => $track->id, 'created_at' => '2026-03-01 10:00:00']);
    subvertFile($this->release, 'newest.mp3', ['track_id' => $track->id, 'mime_type' => 'audio/mpeg', 'created_at' => '2026-04-01 10:00:00']);
    subvertFile($this->release, 'only.mp3', ['track_id' => $other->id, 'mime_type' => 'audio/mpeg', 'created_at' => '2026-01-01 10:00:00']);
    subvertFile($this->release, 'newer.m4a', ['track_id' => $other->id, 'mime_type' => 'audio/mp4', 'created_at' => '2026-04-01 10:00:00']);

    $response = $this->get(route('releases.subvert-package', $this->release))->assertOk();

    expect(zipContents($response))->toBe([
        'cover.jpg' => 'contents of front.jpg',
        '01 Opening.aiff' => 'contents of new master.aiff',
        '02 Closing.mp3' => 'contents of only.mp3',
    ]);
});

test('tracks of a release with several discs carry the disc number first', function () {
    subvertFile($this->release, 'front.jpg', ['kind' => 'cover', 'mime_type' => 'image/jpeg']);

    foreach ([[2, 1, 'Third'], [1, 2, 'Second'], [1, 1, 'First']] as [$disc, $number, $name]) {
        $track = subvertTrack($this->release, ['name' => $name, 'disc_number' => $disc, 'track_number' => $number]);
        subvertFile($this->release, "{$name}.wav", ['track_id' => $track->id]);
    }

    $response = $this->get(route('releases.subvert-package', $this->release))->assertOk();

    expect(array_keys(zipContents($response)))->toBe(['cover.jpg', '1-01 First.wav', '1-02 Second.wav', '2-01 Third.wav']);
});

test('tracks without usable track numbers are numbered by their position', function () {
    subvertFile($this->release, 'front.jpg', ['kind' => 'cover', 'mime_type' => 'image/jpeg']);

    foreach ([[7, 'Numbered'], [null, 'Unnumbered']] as [$number, $name]) {
        $track = subvertTrack($this->release, ['name' => $name, 'track_number' => $number]);
        subvertFile($this->release, "{$name}.wav", ['track_id' => $track->id]);
    }

    $response = $this->get(route('releases.subvert-package', $this->release))->assertOk();

    expect(array_keys(zipContents($response)))->toBe(['cover.jpg', '01 Numbered.wav', '02 Unnumbered.wav']);
});

test('the Subvert ZIP is refused with every reason when the release is not ready', function () {
    $noAudio = subvertTrack($this->release, ['name' => 'Opening', 'track_number' => 1]);
    $wrongFormat = subvertTrack($this->release, ['name' => 'Closing', 'track_number' => 2]);
    subvertFile($this->release, 'closing.ogg', ['track_id' => $wrongFormat->id, 'mime_type' => 'audio/ogg']);

    $this->get(route('releases.subvert-package', $this->release))
        ->assertRedirect(route('releases.show', $this->release))
        ->assertSessionHasErrors(['subvert_package' => implode("\n", [
            'The release has no cover.',
            'Track 1 “Opening” has no audio file.',
            'Track 2 “Closing” only has audio Subvert does not accept (“closing.ogg”); it takes MP3, FLAC, WAV or AIFF.',
        ])]);
});

test('the Subvert ZIP is refused for a single reason', function (Closure $arrange, string $message) {
    $track = subvertTrack($this->release, ['name' => 'Opening', 'track_number' => 1]);
    $arrange($this->release, $track);

    $this->get(route('releases.subvert-package', $this->release))
        ->assertRedirect(route('releases.show', $this->release))
        ->assertSessionHasErrors(['subvert_package' => $message]);
})->with([
    'no cover' => [
        fn (Release $release, Track $track) => subvertFile($release, 'one.wav', ['track_id' => $track->id]),
        'The release has no cover.',
    ],
    'a cover of 10 MB' => [
        function (Release $release, Track $track): void {
            subvertFile($release, 'front.png', ['kind' => 'cover', 'mime_type' => 'image/png'], 10_000_000);
            subvertFile($release, 'one.wav', ['track_id' => $track->id]);
        },
        'The cover is 10.0 MB; Subvert accepts covers under 10 MB.',
    ],
    'a track without audio' => [
        fn (Release $release) => subvertFile($release, 'front.jpg', ['kind' => 'cover', 'mime_type' => 'image/jpeg']),
        'Track 1 “Opening” has no audio file.',
    ],
    'audio in a format Subvert does not take' => [
        function (Release $release, Track $track): void {
            subvertFile($release, 'front.jpg', ['kind' => 'cover', 'mime_type' => 'image/jpeg']);
            subvertFile($release, 'one.m4a', ['track_id' => $track->id, 'mime_type' => 'audio/mp4']);
        },
        'Track 1 “Opening” only has audio Subvert does not accept (“one.m4a”); it takes MP3, FLAC, WAV or AIFF.',
    ],
    'more than 2 GB in total' => [
        function (Release $release, Track $track): void {
            subvertFile($release, 'front.jpg', ['kind' => 'cover', 'mime_type' => 'image/jpeg']);
            subvertFile($release, 'one.wav', ['track_id' => $track->id], 1_200_000_000);
            subvertFile($release, 'two.wav', ['track_id' => subvertTrack($release, ['name' => 'Closing', 'track_number' => 2])->id], 900_000_000);
        },
        'The files add up to 2.10 GB; Subvert accepts ZIP files up to 2 GB.',
    ],
    'a stored file that is gone' => [
        function (Release $release, Track $track): void {
            subvertFile($release, 'front.jpg', ['kind' => 'cover', 'mime_type' => 'image/jpeg']);
            Storage::disk('local')->delete(subvertFile($release, 'one.wav', ['track_id' => $track->id])->path);
        },
        'The audio file “one.wav” of Track 1 “Opening” is missing from storage.',
    ],
]);

test('a release without tracks has nothing to send to Subvert', function () {
    subvertFile($this->release, 'front.jpg', ['kind' => 'cover', 'mime_type' => 'image/jpeg']);

    $this->get(route('releases.subvert-package', $this->release))
        ->assertSessionHasErrors(['subvert_package' => 'The release has no tracks.']);
});

test('a cover just under 10 MB is accepted', function () {
    $track = subvertTrack($this->release, ['name' => 'Opening', 'track_number' => 1]);
    subvertFile($this->release, 'front.png', ['kind' => 'cover', 'mime_type' => 'image/png'], 9_999_999);
    subvertFile($this->release, 'one.wav', ['track_id' => $track->id]);

    $this->get(route('releases.subvert-package', $this->release))->assertOk();
});

test('the ZIP for a store is out of reach for other labels, payees and guests', function (string $route) {
    $url = route($route, $this->release);

    $this->actingAs(labelAdmin(Label::factory()->create()))->get($url)->assertNotFound();

    $payeeUser = User::factory()->create(['current_label_id' => $this->label->id]);
    $this->label->users()->attach($payeeUser, ['role' => LabelRole::Payee->value, 'payee_id' => Payee::factory()->create(['label_id' => $this->label->id])->id]);
    $this->actingAs($payeeUser)->get($url)->assertRedirect(route('portal.index'));

    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));
})->with(['releases.subvert-package', 'releases.bandcamp-package']);

test('the Bandcamp ZIP holds the cover, one lossless file per track and the sheet to copy from', function () {
    $this->release->update(['catalog_number' => 'EX001']);
    $first = subvertTrack($this->release, ['name' => 'Opening', 'track_number' => 1, 'isrc' => 'ZZTST2600001']);
    $second = subvertTrack($this->release, ['name' => 'Closing', 'track_number' => 2]);
    subvertFile($this->release, 'front.jpg', ['kind' => 'cover', 'mime_type' => 'image/jpeg']);
    subvertFile($this->release, 'one.wav', ['track_id' => $first->id, 'created_at' => '2026-01-01 10:00:00']);
    subvertFile($this->release, 'one.mp3', ['track_id' => $first->id, 'mime_type' => 'audio/mpeg', 'created_at' => '2026-04-01 10:00:00']);
    subvertFile($this->release, 'two.flac', ['track_id' => $second->id]);

    $response = $this->get(route('releases.bandcamp-package', $this->release))->assertOk();
    $contents = zipContents($response);

    expect(array_keys($contents))->toBe(['cover.jpg', '01 Opening.wav', '02 Closing.flac', 'release.txt'])
        ->and($contents['release.txt'])->toContain('Title: Example Release', 'Catalog number: EX001', 'Opening', 'ISRC ZZTST2600001')
        ->and($response->headers->get('Content-Disposition'))->toContain('filename="Example Release - Bandcamp.zip"');
});

test('the Bandcamp ZIP is refused for a track that only has an MP3, and not for its size', function () {
    $track = subvertTrack($this->release, ['name' => 'Opening', 'track_number' => 1]);
    subvertFile($this->release, 'front.png', ['kind' => 'cover', 'mime_type' => 'image/png'], 12_000_000);
    $audio = subvertFile($this->release, 'one.mp3', ['track_id' => $track->id, 'mime_type' => 'audio/mpeg']);

    $this->get(route('releases.bandcamp-package', $this->release))
        ->assertRedirect(route('releases.show', $this->release))
        ->assertSessionHasErrors(['bandcamp_package' => 'Track 1 “Opening” only has audio Bandcamp does not accept (“one.mp3”); it takes WAV, AIFF or FLAC.']);

    $audio->update(['original_name' => 'one.wav']);

    $this->get(route('releases.bandcamp-package', $this->release))->assertOk();
});

test('a package is removed from the server once it was sent', function (string $route) {
    $track = subvertTrack($this->release, ['name' => 'Opening', 'track_number' => 1]);
    subvertFile($this->release, 'front.jpg', ['kind' => 'cover', 'mime_type' => 'image/jpeg']);
    subvertFile($this->release, 'one.wav', ['track_id' => $track->id]);
    $response = $this->get(route($route, $this->release))->assertOk();
    $path = $response->baseResponse->getFile()->getPathname();

    ob_start();
    $response->baseResponse->sendContent();
    ob_end_clean();

    expect(file_exists($path))->toBeFalse();
})->with(['releases.subvert-package', 'releases.bandcamp-package', 'releases.package']);
