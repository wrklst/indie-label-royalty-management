<?php

use App\Models\Label;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\Track;
use Illuminate\Support\Facades\Storage;

/**
 * A valid mono 8-bit PCM WAV file holding a quiet tone.
 */
function wavFile(int $seconds): string
{
    $rate = 8000;
    $samples = '';

    for ($index = 0; $index < $rate * $seconds; $index++) {
        $samples .= chr(128 + (int) round(4 * sin($index / 6)));
    }

    return 'RIFF'.pack('V', 36 + strlen($samples)).'WAVE'
        .'fmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate, 1, 8)
        .'data'.pack('V', strlen($samples)).$samples;
}

function trackWithAudio(Release $release, Track $track): ReleaseFile
{
    $file = ReleaseFile::factory()->create([
        'label_id' => $release->label_id, 'release_id' => $release->id, 'track_id' => $track->id, 'kind' => 'audio',
        'path' => "releases/{$track->id}.wav", 'original_name' => "{$track->name}.wav", 'mime_type' => 'audio/wav',
    ]);
    Storage::disk('local')->put($file->path, wavFile(30));

    return $file;
}

const AUDIO_IS_PLAYING = '(() => { const audio = document.querySelector("audio"); return audio !== null && !audio.paused && audio.currentTime > 0; })()';

const PRESS_SPACE_ON_THE_PAGE = 'document.body.dispatchEvent(new KeyboardEvent("keydown", { code: "Space", key: " ", bubbles: true, cancelable: true }))';

test('the player keeps playing across pages, takes a queue and comes back paused after a reload', function () {
    Storage::fake('local');
    $scenario = royaltyScenario();
    $scenario['track']->update(['track_number' => 1]);
    $second = Track::factory()->create([
        'label_id' => $scenario['label']->id, 'release_id' => $scenario['release']->id, 'name' => 'Second Track', 'track_number' => 2,
    ]);
    trackWithAudio($scenario['release'], $scenario['track']);
    trackWithAudio($scenario['release'], $second);
    $this->actingAs($scenario['admin']);

    $page = visit("/releases/{$scenario['release']->id}")
        ->assertMissing('[data-player="dock"]')
        ->click('[aria-label="Play Example Track"]')
        ->assertSeeIn('[data-player="title"]', 'Example Track')
        ->assertPresent('[aria-label="Pause Example Track"]')
        ->assertPresent('[data-player="dock"] [aria-label="Pause"]')
        ->wait(1)
        ->assertScript(AUDIO_IS_PLAYING)
        ->assertScript('document.querySelectorAll("audio").length', 1);

    // Mark the element so a remount or a second element would be noticed.
    $page->script('document.querySelector("audio").dataset.mark = "kept"');

    $page->click('[data-sidebar="menu-button"][href$="/tracks"]')
        ->assertPathIs('/tracks')
        ->assertSee('Every recording across all releases')
        ->assertSeeIn('[data-player="title"]', 'Example Track')
        ->assertScript('document.querySelector("audio").dataset.mark', 'kept')
        ->assertScript(AUDIO_IS_PLAYING)
        ->click('[aria-label="Add Second Track to queue"]')
        ->assertSee('Second Track added to the queue.')
        ->assertSeeIn('[data-player="count"]', '2');

    // Settings pages wrap a second layout around the page.
    $page->click('[data-test="sidebar-menu-button"]')
        ->click('[role="menuitem"][href$="/settings/profile"]')
        ->assertPathIs('/settings/profile')
        ->assertScript('document.querySelector("audio").dataset.mark', 'kept')
        ->assertScript(AUDIO_IS_PLAYING);

    $page->click('[aria-label="Open queue"]')
        ->assertScript('document.querySelectorAll("#player-queue li").length', 2)
        ->assertSeeIn('#player-queue li[data-current]', 'Example Track')
        ->click('[aria-label="Next track"]')
        ->assertSeeIn('[data-player="title"]', 'Second Track')
        ->assertSeeIn('#player-queue li[data-current]', 'Second Track')
        ->wait(1)
        ->assertScript(AUDIO_IS_PLAYING)
        ->assertNoJavaScriptErrors();

    $page->refresh()
        ->assertSeeIn('[data-player="title"]', 'Second Track')
        ->assertSeeIn('[data-player="count"]', '2')
        ->assertPresent('[data-player="dock"] [aria-label="Play"]')
        ->assertScript('document.querySelector("audio").paused')
        ->assertNoJavaScriptErrors();
});

test('a release is played as a whole and the queue can be edited and cleared', function () {
    Storage::fake('local');
    $scenario = royaltyScenario();
    $scenario['track']->update(['track_number' => 1]);
    $second = Track::factory()->create([
        'label_id' => $scenario['label']->id, 'release_id' => $scenario['release']->id, 'name' => 'Second Track', 'track_number' => 2,
    ]);
    Track::factory()->create([
        'label_id' => $scenario['label']->id, 'release_id' => $scenario['release']->id, 'name' => 'Silent Track', 'track_number' => 3,
    ]);
    trackWithAudio($scenario['release'], $scenario['track']);
    trackWithAudio($scenario['release'], $second);
    $this->actingAs($scenario['admin']);

    $page = visit("/releases/{$scenario['release']->id}");

    $page->assertSee('No audio')
        ->press('Play release')
        ->assertSeeIn('[data-player="title"]', 'Example Track')
        ->assertSeeIn('[data-player="count"]', '2')
        ->click('[data-player="dock"] [aria-label="Pause"]')
        ->assertPresent('[aria-label="Resume Example Track"]')
        ->assertScript('document.querySelector("audio").paused')
        ->script(PRESS_SPACE_ON_THE_PAGE);

    $page->assertPresent('[aria-label="Pause Example Track"]')
        ->click('[aria-label="Play Second Track"]')
        ->assertSeeIn('[data-player="title"]', 'Second Track')
        ->click('[aria-label="Open queue"]')
        ->assertSeeIn('#player-queue li:first-child', 'Second Track')
        ->click('[aria-label="Remove Second Track from the queue"]')
        ->assertSeeIn('[data-player="title"]', 'Example Track')
        ->press('Clear queue')
        ->assertMissing('[data-player="dock"]')
        ->assertScript('window.localStorage.getItem("royalties.player")', null)
        ->assertNoJavaScriptErrors();
});

test('a track without a playback copy is reported and skipped', function () {
    Storage::fake('local');
    $scenario = royaltyScenario();
    $scenario['track']->update(['track_number' => 1]);
    $second = Track::factory()->create([
        'label_id' => $scenario['label']->id, 'release_id' => $scenario['release']->id, 'name' => 'Second Track', 'track_number' => 2,
    ]);
    $broken = trackWithAudio($scenario['release'], $scenario['track']);
    trackWithAudio($scenario['release'], $second);
    Storage::disk('local')->delete($broken->path);
    $this->actingAs($scenario['admin']);

    visit("/releases/{$scenario['release']->id}")
        ->press('Play release')
        ->assertSeeIn('[data-player="dock"] [role="alert"]', 'Playback copy is still being prepared. Skipping to the next one')
        ->assertSeeIn('[data-player="title"]', 'Second Track')
        ->wait(1.5)
        ->assertScript(AUDIO_IS_PLAYING);
});

test('the queue does not follow the user to another label', function () {
    Storage::fake('local');
    $scenario = royaltyScenario();
    $other = Label::factory()->create(['name' => 'Other Label']);
    $other->users()->attach($scenario['admin'], ['role' => 'admin']);
    trackWithAudio($scenario['release'], $scenario['track']);
    $this->actingAs($scenario['admin']);

    visit("/releases/{$scenario['release']->id}")
        ->press('Queue release')
        ->assertSeeIn('[data-player="title"]', 'Example Track')
        ->click('[data-slot="sidebar-header"] button')
        ->click('[role="menuitem"]:has-text("Other Label")')
        ->assertPathIs('/dashboard')
        ->assertSee('Other Label')
        ->assertMissing('[data-player="dock"]')
        ->assertScript('window.localStorage.getItem("royalties.player")', null);

});

test('the queue does not survive signing out', function () {
    Storage::fake('local');
    $scenario = royaltyScenario();
    trackWithAudio($scenario['release'], $scenario['track']);
    $this->actingAs($scenario['admin']);

    visit("/releases/{$scenario['release']->id}")
        ->press('Queue release')
        ->assertSeeIn('[data-player="title"]', 'Example Track')
        ->assertScript('window.localStorage.getItem("royalties.player") !== null')
        ->click('[data-test="sidebar-menu-button"]')
        ->click('[data-test="logout-button"]')
        ->assertDontSee('Example Records')
        ->assertScript('window.localStorage.getItem("royalties.player")', null)
        ->assertScript('document.querySelector("audio").getAttribute("src")', null)
        ->assertNoJavaScriptErrors();
});
