<?php

use App\Models\ReleaseFile;
use Illuminate\Support\Facades\Storage;

test('files are uploaded on the release page, the cover shows and audio gets a player', function () {
    Storage::fake('local');
    $scenario = royaltyScenario();
    $scenario['track']->update(['track_number' => 1]);
    $this->actingAs($scenario['admin']);

    $cover = tempnam(sys_get_temp_dir(), 'cover').'.png';
    imagepng(imagecreatetruecolor(20, 20), $cover);
    $audio = sys_get_temp_dir().'/01 Example Track.mp3';
    file_put_contents($audio, 'ID3'.str_repeat("\0", 64));

    $page = visit("/releases/{$scenario['release']->id}")
        ->assertSee('No cover yet')
        ->assertSee('No audio')
        ->select('select[aria-label="Upload as"]', 'cover')
        ->attach('#release-file-input', $cover)
        ->assertSee('uploaded.')
        ->assertPresent('img[alt="Cover of Example Release"]');

    $page->select('select[aria-label="Upload as"]', '')
        ->attach('#release-file-input', $audio)
        ->assertSee('01 Example Track.mp3')
        ->assertPresent('[aria-label="Play Example Track"]')
        ->assertNoJavaScriptErrors();

    $stored = ReleaseFile::query()->withoutGlobalScopes()->where('kind', 'audio')->sole();

    expect($stored->track_id)->toBe($scenario['track']->id);
});

test('track details are edited in a dialog on the release page', function () {
    $scenario = royaltyScenario();
    $this->actingAs($scenario['admin']);

    visit("/releases/{$scenario['release']->id}")
        ->click('[aria-label="Edit details of Example Track"]')
        ->fill('version', 'Club Mix')
        ->fill('bpm', '124')
        ->fill('credits_composers', 'First Writer, Second Writer')
        ->press('Save')
        ->assertSee('Track saved.')
        ->assertSee('(Club Mix)')
        ->assertNoJavaScriptErrors();

    expect($scenario['track']->refresh()->credits)->toBe(['composers' => ['First Writer', 'Second Writer']]);
});

test('delivery details are entered on the release form and shown on the release', function () {
    $scenario = royaltyScenario();
    $this->actingAs($scenario['admin']);

    visit("/releases/{$scenario['release']->id}/edit")
        ->select('status', 'in_progress')
        ->select('release_type', 'ep')
        ->fill('genre', 'Electronic')
        ->fill('blocked_territories', 'ru, il')
        ->fill('press_release', 'A record about examples.')
        ->press('Save release')
        ->assertSee('Release saved.')
        ->assertSee('In progress')
        ->assertSee('Electronic')
        ->assertSee('Russia, Israel')
        ->assertSee('A record about examples.')
        ->assertNoJavaScriptErrors();
});
