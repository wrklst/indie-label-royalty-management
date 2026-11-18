<?php

use App\Enums\AudioPreviewStatus;
use App\Enums\LabelRole;
use App\Jobs\GenerateAudioPreview;
use App\Models\Artist;
use App\Models\Label;
use App\Models\Payee;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\Track;
use App\Models\User;
use App\Services\Releases\AudioPreviews;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\Factory;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    Process::preventStrayProcesses();
    $this->label = Label::factory()->create(['slug' => 'example-records']);
    $this->release = Release::factory()->create(['label_id' => $this->label->id, 'name' => 'Example Release']);
    $this->actingAs(labelAdmin($this->label));
});

function storedAudio(Release $release, array $attributes = [], string $contents = 'the untouched master'): ReleaseFile
{
    $file = ReleaseFile::factory()->create([
        'label_id' => $release->label_id, 'release_id' => $release->id, 'kind' => 'audio', 'mime_type' => 'audio/wav',
        'original_name' => 'master.wav', 'path' => "releases/{$release->label_id}/{$release->id}/".fake()->uuid().'.wav',
        'checksum' => hash('sha256', $contents), ...$attributes,
    ]);
    Storage::disk('local')->put($file->path, $contents);

    return $file;
}

/**
 * A file whose playback copy has been made.
 */
function convertedAudio(Release $release, array $attributes = []): ReleaseFile
{
    $file = storedAudio($release, $attributes);
    $file->update(['preview_status' => 'ready', 'preview_path' => dirname($file->path)."/previews/{$file->id}-abcdef.mp3"]);
    Storage::disk('local')->put($file->preview_path, '0123456789');

    return $file;
}

/**
 * Stand in for ffmpeg: write what it would have written and report like it.
 */
function fakeFfmpeg(string $report = "  Duration: 00:03:25.40, bitrate: 2304 kb/s\n", int $exitCode = 0): void
{
    Process::fake(function (PendingProcess $process) use ($report, $exitCode) {
        if ($exitCode === 0 && in_array('libmp3lame', $process->command, true)) {
            file_put_contents((string) last($process->command), 'the mp3 copy');
        }

        return Process::result(errorOutput: $report, exitCode: $exitCode);
    });
}

test('an uploaded audio file is queued for its playback copy', function () {
    $this->post(route('release-files.store', $this->release), ['file' => UploadedFile::fake()->create('master.wav', 50, 'audio/wav')])
        ->assertSessionHasNoErrors();

    $file = ReleaseFile::query()->sole();

    expect($file->preview_status)->toBe(AudioPreviewStatus::Pending);
    Queue::assertPushed(GenerateAudioPreview::class, fn (GenerateAudioPreview $job): bool => $job->releaseFileId === $file->id && ! $job->force);
});

test('a file that is not audio is not queued', function () {
    $this->post(route('release-files.store', $this->release), ['file' => UploadedFile::fake()->create('Press kit.pdf', 20, 'application/pdf')])
        ->assertSessionHasNoErrors();

    expect(ReleaseFile::query()->sole()->preview_status)->toBeNull();
    Queue::assertNothingPushed();
});

test('an uploaded MP3 is playable at once', function () {
    $this->post(route('release-files.store', $this->release), ['file' => UploadedFile::fake()->create('single.mp3', 50, 'audio/mpeg')])
        ->assertSessionHasNoErrors();

    expect(ReleaseFile::query()->sole()->preview_status)->toBe(AudioPreviewStatus::Ready);
});

test('a file that becomes audio is queued, and one that stops being audio loses its playback copy', function () {
    $document = storedAudio($this->release, ['kind' => 'other']);
    $audio = convertedAudio($this->release);
    $preview = $audio->preview_path;

    $this->patch(route('release-files.update', $document->id), ['kind' => 'audio'])->assertSessionHasNoErrors();
    $this->patch(route('release-files.update', $audio->id), ['kind' => 'other'])->assertSessionHasNoErrors();

    expect($document->refresh()->preview_status)->toBe(AudioPreviewStatus::Pending)
        ->and($audio->refresh()->only(['preview_status', 'preview_path']))->toBe(['preview_status' => null, 'preview_path' => null]);
    Queue::assertPushed(GenerateAudioPreview::class, 1);
    Queue::assertPushed(GenerateAudioPreview::class, fn (GenerateAudioPreview $job): bool => $job->releaseFileId === $document->id);
    Storage::disk('local')->assertMissing($preview);
});

test('the master is converted by ffmpeg, called with separate arguments and never through a shell', function () {
    $artist = Artist::factory()->create(['label_id' => $this->label->id, 'name' => 'Example Artist']);
    $this->release->update(['artist_id' => $artist->id, 'version' => 'Deluxe']);
    $track = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $this->release->id, 'name' => 'Opening"; rm -rf ~ #', 'version' => 'Club Mix', 'duration' => null]);
    $file = storedAudio($this->release, ['track_id' => $track->id, 'original_name' => '$(reboot) `id`.wav']);
    fakeFfmpeg();

    $ready = app(AudioPreviews::class)->generate($file);

    $file->refresh();
    $source = Storage::disk('local')->path($file->path);

    expect($ready)->toBeTrue()
        ->and($file->preview_status)->toBe(AudioPreviewStatus::Ready)
        ->and($file->preview_error)->toBeNull()
        ->and($file->preview_path)->toBe("releases/{$this->label->id}/{$this->release->id}/previews/{$file->id}-".substr($file->checksum, 0, 16).'.mp3')
        ->and($file->duration)->toBe(205)
        ->and($track->refresh()->duration)->toBe(205)
        ->and(Storage::disk('local')->get($file->preview_path))->toBe('the mp3 copy')
        ->and(Storage::disk('local')->get($file->path))->toBe('the untouched master')
        ->and(Storage::disk('local')->allFiles())->toHaveCount(2);

    Process::assertRanTimes(fn (PendingProcess $process): bool => is_array($process->command), 1);
    Process::assertRan(function (PendingProcess $process) use ($source): bool {
        $target = last($process->command);

        return $process->timeout === 600 && array_slice($process->command, 0, -1) === [
            'ffmpeg', '-hide_banner', '-nostdin', '-y', '-i', $source,
            '-map', '0:a:0', '-vn', '-sn', '-dn', '-map_metadata', '-1', '-map_chapters', '-1',
            '-c:a', 'libmp3lame', '-b:a', '320k', '-ar', '44100', '-ac', '2', '-id3v2_version', '3',
            '-metadata', 'title=Opening"; rm -rf ~ # (Club Mix)',
            '-metadata', 'artist=Example Artist',
            '-metadata', 'album=Example Release (Deluxe)',
            '-f', 'mp3',
        ] && str_starts_with($target, dirname($source).'/previews/');
    });
});

test('the binary and the quality of the playback copy can be configured', function () {
    config(['royalties.audio' => ['ffmpeg' => '/opt/example/bin/ffmpeg', 'bitrate' => 192, 'sample_rate' => 48000, 'timeout' => 120]]);
    $file = storedAudio($this->release);
    fakeFfmpeg();

    app(AudioPreviews::class)->generate($file);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command[0] === '/opt/example/bin/ffmpeg'
        && $process->timeout === 120
        && array_slice($process->command, array_search('-b:a', $process->command, true), 4) === ['-b:a', '192k', '-ar', '48000']
        && ! in_array('-metadata', $process->command, true));
});

test('a track that already has a length keeps it', function () {
    $track = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $this->release->id, 'duration' => 180]);
    $file = storedAudio($this->release, ['track_id' => $track->id]);
    fakeFfmpeg();

    app(AudioPreviews::class)->generate($file);

    expect($track->refresh()->duration)->toBe(180)
        ->and($file->refresh()->duration)->toBe(205);
});

test('a failed conversion is recorded with what ffmpeg said', function () {
    $file = storedAudio($this->release);
    fakeFfmpeg("ffmpeg version 9\n[in#0] Error opening input: Invalid data found when processing input\nError opening input file master.wav.\n", 183);

    $ready = app(AudioPreviews::class)->generate($file);

    expect($ready)->toBeFalse()
        ->and($file->refresh()->preview_status)->toBe(AudioPreviewStatus::Failed)
        ->and($file->preview_error)->toContain('Invalid data found when processing input')
        ->and($file->preview_path)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([$file->path]);
});

test('a conversion that cannot be started is recorded as failed', function () {
    $file = storedAudio($this->release);
    Process::fake(fn () => throw new RuntimeException('The command "ffmpeg" could not be found.'));

    app(AudioPreviews::class)->generate($file);

    expect($file->refresh()->preview_status)->toBe(AudioPreviewStatus::Failed)
        ->and($file->preview_error)->toBe('ffmpeg could not be run: The command "ffmpeg" could not be found.');
});

test('a master missing from storage is recorded as failed without calling ffmpeg', function () {
    $file = storedAudio($this->release);
    Storage::disk('local')->delete($file->path);
    Process::fake();

    app(AudioPreviews::class)->generate($file);

    expect($file->refresh()->preview_error)->toBe('The stored file is missing.');
    Process::assertNothingRan();
});

test('an MP3 is its own playback copy and is only measured', function () {
    $track = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $this->release->id, 'duration' => null]);
    $file = storedAudio($this->release, ['track_id' => $track->id, 'mime_type' => 'audio/mpeg', 'original_name' => 'single.mp3']);
    fakeFfmpeg("  Duration: 00:00:59.60, start: 0.000000\nAt least one output file must be specified\n", 1);

    $ready = app(AudioPreviews::class)->generate($file);

    expect($ready)->toBeTrue()
        ->and($file->refresh()->preview_status)->toBe(AudioPreviewStatus::Ready)
        ->and($file->preview_path)->toBeNull()
        ->and($file->duration)->toBe(60)
        ->and($track->refresh()->duration)->toBe(60)
        ->and(Storage::disk('local')->allFiles())->toBe([$file->path]);
    Process::assertDidntRun(fn (PendingProcess $process): bool => in_array('libmp3lame', $process->command, true));
});

test('a finished playback copy is not made again unless asked to', function () {
    $file = convertedAudio($this->release);
    $old = $file->preview_path;
    fakeFfmpeg();

    app(AudioPreviews::class)->generate($file);
    Process::assertNothingRan();

    app(AudioPreviews::class)->generate($file, force: true);

    expect($file->refresh()->preview_path)->not->toBe($old)
        ->and(Storage::disk('local')->allFiles())->toEqualCanonicalizing([$file->path, $file->preview_path]);
});

test('the playback copy is deleted with its file', function () {
    $file = convertedAudio($this->release);
    $other = convertedAudio($this->release);

    $this->delete(route('release-files.destroy', $file->id))->assertRedirect();

    expect(Storage::disk('local')->allFiles())->toEqualCanonicalizing([$other->path, $other->preview_path]);
});

test('the queued job converts its file and copes with one deleted in the meantime', function () {
    $file = storedAudio($this->release);
    fakeFfmpeg();

    (new GenerateAudioPreview($file->id))->handle(app(AudioPreviews::class));
    (new GenerateAudioPreview($file->id + 100))->handle(app(AudioPreviews::class));

    expect($file->refresh()->preview_status)->toBe(AudioPreviewStatus::Ready)
        ->and((new GenerateAudioPreview($file->id))->uniqueId())->toBe((string) $file->id)
        ->and((new GenerateAudioPreview($file->id))->timeout)->toBe(660);
});

test('a job that gave up marks its file as failed', function () {
    $file = storedAudio($this->release, ['preview_status' => 'pending']);

    (new GenerateAudioPreview($file->id))->failed(new RuntimeException('The worker was stopped.'));

    expect($file->refresh()->preview_status)->toBe(AudioPreviewStatus::Failed)
        ->and($file->preview_error)->toBe('The worker was stopped.');
});

test('the player gets the MP3 copy, with seeking, and never a cached one', function () {
    $file = convertedAudio($this->release, ['original_name' => 'Opening master.wav']);

    $response = $this->get(route('release-files.show', [$file->id, 'stream' => 1]), ['Range' => 'bytes=2-5']);

    $response->assertStatus(206)
        ->assertHeader('Content-Type', 'audio/mpeg')
        ->assertHeader('Content-Range', 'bytes 2-5/10')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->baseResponse->getFile()->getPathname())->toBe(Storage::disk('local')->path($file->preview_path))
        ->and($response->headers->get('Content-Disposition'))->toBe('inline; filename="Opening master.mp3"')
        ->and($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
});

test('a download is always the untouched master', function () {
    $file = convertedAudio($this->release);

    $response = $this->get(route('release-files.show', [$file->id, 'stream' => 1, 'download' => 1]))->assertOk();

    expect($response->baseResponse->getFile()->getPathname())->toBe(Storage::disk('local')->path($file->path))
        ->and($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});

test('without a finished copy the player gets the master when browsers can play it', function (array $attributes) {
    $file = storedAudio($this->release, $attributes);

    $response = $this->get(route('release-files.show', [$file->id, 'stream' => 1]))->assertOk()->assertHeader('Content-Type', $file->mime_type);

    expect($response->baseResponse->getFile()->getPathname())->toBe(Storage::disk('local')->path($file->path));
})->with([
    'a WAV still waiting' => [['preview_status' => 'pending']],
    'a WAV whose conversion failed' => [['preview_status' => 'failed', 'preview_error' => 'broken']],
    'a WAV never queued' => [[]],
    'an MP3' => [['mime_type' => 'audio/mpeg', 'original_name' => 'single.mp3', 'preview_status' => 'ready']],
    'a copy recorded but gone from disk' => [['preview_status' => 'ready', 'preview_path' => 'releases/gone.mp3']],
]);

test('without a finished copy a format browsers cannot play answers 409 with the reason', function (?string $status, string $message) {
    $file = storedAudio($this->release, ['mime_type' => 'audio/x-ms-wma', 'original_name' => 'master.wma', 'preview_status' => $status]);

    $this->get(route('release-files.show', [$file->id, 'stream' => 1]))
        ->assertConflict()
        ->assertExactJson(['message' => $message, 'preview_status' => $status]);
})->with([
    'waiting' => ['pending', 'The playback copy of this file is not ready yet.'],
    'never queued' => [null, 'The playback copy of this file is not ready yet.'],
    'failed' => ['failed', 'This file could not be converted for playback.'],
]);

test('asking for a stream of a file that is not audio changes nothing', function () {
    $file = storedAudio($this->release, ['kind' => 'document', 'mime_type' => 'application/pdf', 'original_name' => 'kit.pdf']);

    $this->get(route('release-files.show', [$file->id, 'stream' => 1]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('a conversion can be started again from the file table', function () {
    $file = storedAudio($this->release, ['preview_status' => 'failed', 'preview_error' => 'broken']);
    $document = storedAudio($this->release, ['kind' => 'document']);

    $this->post(route('release-files.preview', $file->id))->assertRedirect();
    $this->post(route('release-files.preview', $document->id))->assertNotFound();

    expect($file->refresh()->only(['preview_status', 'preview_error']))->toBe(['preview_status' => AudioPreviewStatus::Pending, 'preview_error' => null]);
    Queue::assertPushed(GenerateAudioPreview::class, 1);
    Queue::assertPushed(GenerateAudioPreview::class, fn (GenerateAudioPreview $job): bool => $job->releaseFileId === $file->id && $job->force);
});

test('the file table is told how far each playback copy is', function () {
    $failed = storedAudio($this->release, ['preview_status' => 'failed', 'preview_error' => 'Invalid data found']);
    $mp3 = storedAudio($this->release, ['mime_type' => 'audio/mpeg', 'original_name' => 'single.mp3', 'preview_status' => 'ready']);
    $untouched = storedAudio($this->release);
    storedAudio($this->release, ['kind' => 'document']);
    storedAudio(Release::factory()->create(['label_id' => $this->label->id]));

    $this->getJson(route('release-files.previews', $this->release))->assertOk()->assertExactJson([
        ['id' => $failed->id, 'status' => 'failed', 'error' => 'Invalid data found', 'converted' => true],
        ['id' => $mp3->id, 'status' => 'ready', 'error' => null, 'converted' => false],
        ['id' => $untouched->id, 'status' => null, 'error' => null, 'converted' => true],
    ]);
});

test('playback copies are out of reach for other labels, payees and guests', function () {
    $file = convertedAudio($this->release);
    $requests = [
        fn () => $this->get(route('release-files.show', [$file->id, 'stream' => 1])),
        fn () => $this->post(route('release-files.preview', $file->id)),
        fn () => $this->get(route('release-files.previews', $this->release)),
    ];

    $this->actingAs(labelAdmin(Label::factory()->create()));
    collect($requests)->each(fn (Closure $request) => $request()->assertNotFound());

    $payeeUser = User::factory()->create(['current_label_id' => $this->label->id]);
    $this->label->users()->attach($payeeUser, ['role' => LabelRole::Payee->value, 'payee_id' => Payee::factory()->create(['label_id' => $this->label->id])->id]);
    $this->actingAs($payeeUser);
    $requests[0]()->assertRedirect(route('portal.index'));
    $requests[1]()->assertForbidden();
    $requests[2]()->assertRedirect(route('portal.index'));

    auth()->logout();
    collect($requests)->each(fn (Closure $request) => $request()->assertRedirect(route('login')));

    Queue::assertNothingPushed();
});

test('the command converts what has no playback copy yet and reports each file', function () {
    $missing = storedAudio($this->release, ['original_name' => 'first.wav']);
    $pending = storedAudio($this->release, ['original_name' => 'second.wav', 'preview_status' => 'pending']);
    $done = convertedAudio($this->release, ['original_name' => 'third.wav']);
    $elsewhere = storedAudio(Release::factory()->create(['label_id' => Label::factory()]), ['original_name' => 'elsewhere.wav']);
    fakeFfmpeg();

    $this->artisan('royalties:generate-previews', ['--label' => 'example-records'])
        ->expectsOutputToContain('first.wav')
        ->expectsOutputToContain('second.wav')
        ->doesntExpectOutputToContain('third.wav')
        ->doesntExpectOutputToContain('elsewhere.wav')
        ->expectsOutputToContain('2 made, 1 already there, 0 failed.')
        ->assertSuccessful();

    expect($missing->refresh()->preview_status)->toBe(AudioPreviewStatus::Ready)
        ->and($pending->refresh()->preview_status)->toBe(AudioPreviewStatus::Ready)
        ->and($done->refresh()->preview_path)->toEndWith('-abcdef.mp3')
        ->and($elsewhere->refresh()->preview_status)->toBeNull();
});

test('the command can be limited to a release and made to convert everything again', function () {
    $done = convertedAudio($this->release);
    $other = storedAudio(Release::factory()->create(['label_id' => $this->label->id]));
    fakeFfmpeg();

    $this->artisan('royalties:generate-previews', ['--release' => $this->release->id, '--force' => true])
        ->expectsOutputToContain('1 made, 0 already there, 0 failed.')
        ->assertSuccessful();

    expect($done->refresh()->preview_path)->not->toEndWith('-abcdef.mp3')
        ->and($other->refresh()->preview_status)->toBeNull();
});

test('the command reports failures and an unknown label', function () {
    storedAudio($this->release, ['original_name' => 'broken.wav']);
    fakeFfmpeg("Error opening input: Invalid data found when processing input\n", 183);

    $this->artisan('royalties:generate-previews')
        ->expectsOutputToContain('broken.wav: Error opening input: Invalid data found when processing input')
        ->expectsOutputToContain('0 made, 0 already there, 1 failed.')
        ->assertFailed();

    $this->artisan('royalties:generate-previews', ['--label' => 'no-such-label'])
        ->expectsOutputToContain('There is no label “no-such-label”.')
        ->assertFailed();
});

test('ffmpeg really turns a WAV master into a tagged MP3', function () {
    Process::swap(new Factory);
    $track = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $this->release->id, 'name' => 'Opening "Night" & Day', 'duration' => null]);

    // Two seconds of a 440 Hz tone: 8 kHz, mono, 16 bit.
    $samples = '';
    for ($index = 0; $index < 16_000; $index++) {
        $samples .= pack('v', (int) (12_000 * sin(2 * M_PI * 440 * $index / 8000)) & 0xFFFF);
    }
    $wav = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16_000, 2, 16).'data'.pack('V', strlen($samples)).$samples;
    $file = storedAudio($this->release, ['track_id' => $track->id, 'original_name' => 'It\'s a "test"; $HOME.wav'], $wav);

    $ready = app(AudioPreviews::class)->generate($file);

    $file->refresh();
    $mp3 = (string) Storage::disk('local')->get((string) $file->preview_path);

    expect($file->preview_error)->toBeNull()
        ->and($ready)->toBeTrue()
        ->and($file->duration)->toBe(2)
        ->and($track->refresh()->duration)->toBe(2)
        ->and(substr($mp3, 0, 3))->toBe('ID3')
        ->and($mp3)->toContain('Opening "Night" & Day')->toContain('Example Release')
        ->and(strlen($mp3))->toBeGreaterThan(50_000)
        ->and(Storage::disk('local')->get($file->path))->toBe($wav);
})->skip(fn (): bool => (new ExecutableFinder)->find((string) config('royalties.audio.ffmpeg')) === null, 'ffmpeg is not installed.');
