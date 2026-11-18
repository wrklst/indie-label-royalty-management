<?php

use App\Models\ReleaseFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

test('the release page lists what keeps the Subvert ZIP from being made', function () {
    Storage::fake('local');
    $scenario = royaltyScenario();
    $scenario['track']->update(['track_number' => 1]);
    $this->actingAs($scenario['admin']);

    visit("/releases/{$scenario['release']->id}")
        ->click('Delivery')
        ->click('Subvert ZIP')
        ->assertSee('The Subvert ZIP cannot be made yet')
        ->assertSee('The release has no cover.')
        ->assertSee('Track 1 “Example Track” has no audio file.')
        ->press('Close')
        ->assertDontSee('The Subvert ZIP cannot be made yet')
        ->assertNoJavaScriptErrors();
});

test('the file table shows the state of a playback copy and starts a conversion', function () {
    Storage::fake('local');
    $scenario = royaltyScenario();
    $this->actingAs($scenario['admin']);
    $file = ReleaseFile::factory()->create([
        'label_id' => $scenario['release']->label_id, 'release_id' => $scenario['release']->id, 'track_id' => $scenario['track']->id,
        'kind' => 'audio', 'path' => 'releases/browser/master.wav', 'original_name' => 'master.wav', 'mime_type' => 'audio/wav',
        'preview_status' => 'failed', 'preview_error' => 'Invalid data found when processing input',
    ]);
    Storage::disk('local')->put($file->path, 'the master');
    Process::fake(function (PendingProcess $process) {
        file_put_contents((string) last($process->command), 'the mp3 copy');

        return Process::result(errorOutput: 'Duration: 00:01:00.00');
    });

    visit("/releases/{$scenario['release']->id}")
        ->assertSee('Playback copy failed')
        ->assertPresent('[title="Invalid data found when processing input"]')
        ->press('Convert again')
        ->assertSee('MP3 playback copy ready')
        ->assertDontSee('Convert again')
        ->assertNoJavaScriptErrors();

    expect($file->refresh()->preview_status->value)->toBe('ready');
});
