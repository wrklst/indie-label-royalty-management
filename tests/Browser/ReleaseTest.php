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
        ->attach('[data-release-upload] input[type="file"]', $cover)
        ->assertSee('uploaded.')
        ->assertPresent('img[alt="Cover of Example Release"]');

    $page->select('select[aria-label="Upload as"]', '')
        ->attach('[data-release-upload] input[type="file"]', $audio)
        ->assertSee('01 Example Track.mp3')
        ->assertPresent('[aria-label="Play Example Track"]')
        ->assertNoJavaScriptErrors();

    $stored = ReleaseFile::query()->withoutGlobalScopes()->where('kind', 'audio')->sole();

    expect($stored->track_id)->toBe($scenario['track']->id);
});

test('several files added at once are all stored', function () {
    Storage::fake('local');
    $scenario = royaltyScenario();
    $this->actingAs($scenario['admin']);

    $page = visit("/releases/{$scenario['release']->id}");

    $page->script(<<<'JS'
        const input = document.querySelector('[data-release-upload] input[type="file"]');
        const files = new DataTransfer();
        ['one.txt', 'two.txt', 'three.txt', 'four.txt'].forEach((name) => files.items.add(new File([name], name, { type: 'text/plain' })));
        input.files = files.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    JS);

    $stored = fn (): int => ReleaseFile::query()->withoutGlobalScopes()->count();

    for ($waited = 0; $waited < 40 && $stored() < 4; $waited++) {
        $page->wait(0.25);
    }

    $page->assertSee('one.txt')
        ->assertSee('four.txt')
        ->assertMissing('.filepond--item')
        ->assertNoJavaScriptErrors();

    expect($stored())->toBe(4);
});

test('track details are edited in the one track dialog, from the release page and from the track page', function () {
    $scenario = royaltyScenario();
    $this->actingAs($scenario['admin']);

    visit("/releases/{$scenario['release']->id}")
        ->click('[aria-label="Edit Example Track"]')
        ->fill('version', 'Club Mix')
        ->fill('bpm', '124')
        ->fill('credits_composers', 'First Writer, Second Writer')
        ->press('Save track')
        ->assertSee('Track saved.')
        ->assertSee('(Club Mix)')
        ->assertNoJavaScriptErrors();

    expect($scenario['track']->refresh()->credits)->toBe(['composers' => ['First Writer', 'Second Writer']]);

    visit("/tracks/{$scenario['track']->id}")
        ->press('Edit')
        ->assertValue('[role="dialog"] input[name="version"]', 'Club Mix')
        ->assertValue('[role="dialog"] input[name="credits[composers]"]', 'First Writer, Second Writer')
        ->fill('musical_key', 'F minor')
        ->press('Save track')
        ->assertSee('Track saved.')
        ->assertPathIs("/tracks/{$scenario['track']->id}")
        ->assertNoJavaScriptErrors();

    expect($scenario['track']->refresh()->musical_key)->toBe('F minor');

    visit('/tracks')
        ->click('[aria-label="Edit Example Track"]')
        ->assertValue('[role="dialog"] input[name="musical_key"]', 'F minor')
        ->assertNoJavaScriptErrors();
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

test('a platform link is added to a release by hand and listed with its platform', function () {
    $scenario = royaltyScenario();
    $this->actingAs($scenario['admin']);

    visit("/releases/{$scenario['release']->id}")
        ->assertSee('Where to find it')
        ->assertSee('No links yet')
        ->press('Add link')
        ->fill('#platform_link_url', 'https://example.bandcamp.com/album/example-release')
        ->press('Save link')
        ->assertSee('Link added.')
        ->assertSeeIn('[data-platform-links] li span.font-medium', 'Bandcamp')
        ->assertSeeIn('[data-platform-links] a', 'https://example.bandcamp.com/album/example-release')
        ->assertNoJavaScriptErrors();
});

test('the files of a release are shown by type, images with a thumbnail and audio with play and queue buttons', function () {
    $scenario = royaltyScenario();
    $this->actingAs($scenario['admin']);
    $file = fn (array $attributes): ReleaseFile => ReleaseFile::factory()->create(['label_id' => $scenario['release']->label_id, 'release_id' => $scenario['release']->id, ...$attributes]);
    $artwork = $file(['kind' => 'artwork', 'mime_type' => 'image/png', 'original_name' => 'poster.png']);
    $file(['kind' => 'audio', 'mime_type' => 'audio/mpeg', 'original_name' => 'loose take.mp3', 'track_id' => null]);
    $file(['kind' => 'document', 'mime_type' => 'application/pdf', 'original_name' => 'press sheet.pdf']);

    visit("/releases/{$scenario['release']->id}")
        ->assertSee('poster.png')
        ->assertPresent("img[src*='/{$artwork->id}?size=thumb']")
        ->assertPresent('[aria-label="Play loose take.mp3"]')
        ->assertPresent('[aria-label="Add loose take.mp3 to queue"]')
        ->click('[role="tab"]:has-text("Audio")')
        ->assertSee('loose take.mp3')
        ->assertDontSee('poster.png')
        ->assertDontSee('press sheet.pdf')
        ->click('[role="tab"]:has-text("All")')
        ->assertSee('press sheet.pdf')
        ->assertNoJavaScriptErrors();
});
