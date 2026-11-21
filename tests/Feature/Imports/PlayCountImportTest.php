<?php

use App\Models\Label;
use App\Models\PlayCount;
use App\Models\Track;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

function playCountFile(string $content, string $name = 'example-plays.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

test('a portal export is read by its columns and matched to tracks by name', function () {
    $label = Label::factory()->create();
    $track = Track::factory()->create(['label_id' => $label->id, 'name' => 'Example Track']);
    $remix = Track::factory()->create(['label_id' => $label->id, 'name' => 'Example Track (Example Remix)']);

    $this->actingAs(labelAdmin($label))->post(route('play-counts.store'), [
        'file' => playCountFile("song,listeners,streams\nExample Track,40,120\nExample Track - Example Remix,5,9\nUnknown Song,1,2\nTotal,,\n"),
        'platform' => 'spotify', 'from' => '2026-01-01', 'to' => '2026-01-31',
    ])->assertRedirect(route('play-counts.index'));

    $counts = PlayCount::query()->withoutGlobalScope('label')->where('label_id', $label->id)->orderBy('id')->get();

    expect($counts)->toHaveCount(3)
        ->and($counts[0])->toMatchArray(['track_id' => $track->id, 'platform' => 'spotify', 'plays' => 120, 'listeners' => 40, 'source_file' => 'example-plays.csv'])
        ->and($counts[0]->period_start->toDateString())->toBe('2026-01-01')
        ->and($counts[0]->period_end->toDateString())->toBe('2026-01-31')
        ->and($counts[1]->track_id)->toBe($remix->id)
        ->and($counts[2])->toMatchArray(['track_id' => null, 'track_name' => 'Unknown Song', 'plays' => 2]);

    $this->get(route('play-counts.index'))->assertInertia(fn (Assert $page) => $page
        ->has('files', 1)
        ->where('files.0.counts', 3)
        ->where('files.0.matched', 2)
        ->where('files.0.plays', 131)
        ->where('files.0.platforms', [['key' => 'spotify', 'name' => 'Spotify']])
        ->where('files.0.unmatched', ['Unknown Song']));
});

test('the template names platform, period and ISRC on each line, and a recording on two releases is one', function () {
    $label = Label::factory()->create();
    $single = Track::factory()->create(['label_id' => $label->id, 'name' => 'Example Track', 'isrc' => 'ZZTST2600001']);
    Track::factory()->create(['label_id' => $label->id, 'name' => 'Example Track (Album Version)', 'isrc' => 'ZZTST2600001']);

    $this->actingAs(labelAdmin($label))->post(route('play-counts.store'), [
        'file' => playCountFile("platform,isrc,track,artist,from,to,plays,listeners\nTidal,ZZ-TST-26-00001,Some Other Spelling,Example Artist,2026-01-01,2026-03-31,1250,\n"),
    ])->assertSessionHasNoErrors();

    $count = PlayCount::query()->withoutGlobalScope('label')->where('label_id', $label->id)->sole();

    expect($count)->toMatchArray(['track_id' => $single->id, 'platform' => 'tidal', 'plays' => 1250, 'listeners' => null, 'artist_name' => 'Example Artist'])
        ->and($count->period_end->toDateString())->toBe('2026-03-31');
});

test('a newer file replaces counts of the same track and platform for the days it covers', function () {
    $label = Label::factory()->create();
    $track = Track::factory()->create(['label_id' => $label->id, 'name' => 'Example Track']);
    $shared = ['label_id' => $label->id, 'track_id' => $track->id, 'track_name' => 'Example Track'];
    PlayCount::factory()->create([...$shared, 'platform' => 'spotify', 'plays' => 10, 'period_start' => '2026-01-01', 'period_end' => '2026-01-31']);
    $february = PlayCount::factory()->create([...$shared, 'platform' => 'spotify', 'plays' => 20, 'period_start' => '2026-02-01', 'period_end' => '2026-02-28']);
    $tidal = PlayCount::factory()->create([...$shared, 'platform' => 'tidal', 'plays' => 30, 'period_start' => '2026-01-01', 'period_end' => '2026-01-31']);
    $other = PlayCount::factory()->create(['platform' => 'spotify', 'track_name' => 'Example Track', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31']);

    $this->actingAs(labelAdmin($label))->post(route('play-counts.store'), [
        'file' => playCountFile("track,date,plays\nExample Track,2026-01-15,7\n"), 'platform' => 'spotify',
    ])->assertSessionHasNoErrors();

    expect(PlayCount::query()->withoutGlobalScope('label')->where('label_id', $label->id)->orderBy('plays')->pluck('plays')->all())->toBe([7, 20, 30])
        ->and($february->fresh())->not->toBeNull()
        ->and($tidal->fresh())->not->toBeNull()
        ->and($other->fresh())->not->toBeNull();
});

test('a file that cannot be read as play counts is refused and nothing is stored', function (string $content, array $fields, string $message) {
    $label = Label::factory()->create();
    Track::factory()->create(['label_id' => $label->id, 'name' => 'Example Track']);

    $this->actingAs(labelAdmin($label))->post(route('play-counts.store'), ['file' => playCountFile($content), ...$fields])
        ->assertSessionHasErrors(['file' => $message]);

    expect(PlayCount::query()->withoutGlobalScope('label')->count())->toBe(0);
})->with([
    'no period' => ["song,streams\nExample Track,5\n", ['platform' => 'spotify'], 'The file has no dates. Say which period it covers.'],
    'no platform' => ["song,streams\nExample Track,5\n", ['from' => '2026-01-01', 'to' => '2026-01-31'], 'The file does not name the platform. Choose it.'],
    'unknown platform' => ["platform,song,streams,date\nExample Jukebox,Example Track,5,2026-01-01\n", [], 'Line 2: "Example Jukebox" is not a platform this installation knows.'],
    'no play count column' => ["song,revenue\nExample Track,5\n", ['platform' => 'spotify'], 'The file needs a column with the play count, called "plays" or "streams", and one naming the track, called "track", "song" or "isrc". Found: song, revenue.'],
    'second line breaks' => ["song,streams,date\nExample Track,5,2026-01-01\nExample Track,5,01/02/2026\n", ['platform' => 'spotify'], 'Line 3: The date "01/02/2026" in "date" can be read two ways. Use the format 2026-03-31.'],
]);

test('deleting a file removes its counts and only those of the own label', function () {
    $label = Label::factory()->create();
    PlayCount::factory()->count(2)->create(['label_id' => $label->id, 'batch' => 'EXAMPLEBATCH']);
    $kept = PlayCount::factory()->create(['label_id' => $label->id, 'batch' => 'OTHERBATCH']);
    $foreign = PlayCount::factory()->create(['batch' => 'EXAMPLEBATCH']);

    $this->actingAs(labelAdmin($label))->delete(route('play-counts.destroy', 'EXAMPLEBATCH'))->assertRedirect(route('play-counts.index'));

    expect(PlayCount::query()->withoutGlobalScope('label')->pluck('id')->all())->toEqualCanonicalizing([$kept->id, $foreign->id]);
});

test('play counts are for label admins', function () {
    $this->get(route('play-counts.index'))->assertRedirect(route('login'));
    $this->post(route('play-counts.store'))->assertRedirect(route('login'));
});
