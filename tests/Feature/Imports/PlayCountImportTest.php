<?php

use App\Models\Label;
use App\Models\PlayCount;
use App\Models\Release;
use App\Models\Track;
use App\Models\Transaction;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

function playCountFile(string $content, string $name = 'example-plays.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

test('a portal export is read by its columns and matched to tracks by name', function () {
    $this->travelTo('2026-02-10 12:00:00');
    $label = Label::factory()->create();
    $track = Track::factory()->create(['label_id' => $label->id, 'name' => 'Example Track']);
    $remix = Track::factory()->create(['label_id' => $label->id, 'name' => 'Example Track (Example Remix)']);

    $this->actingAs(labelAdmin($label))->post(route('play-counts.store'), [
        'file' => playCountFile("song,listeners,streams,saves\nExample Track,40,120,3\nExample Track - Example Remix,5,9,0\nUnknown Song,1,2,0\nTotal,,,\n"),
    ])->assertRedirect(route('play-counts.index'));

    $counts = PlayCount::query()->withoutGlobalScope('label')->where('label_id', $label->id)->orderBy('id')->get();

    expect($counts)->toHaveCount(3)
        ->and($counts[0])->toMatchArray(['track_id' => $track->id, 'platform' => 'spotify', 'plays' => 120, 'listeners' => 40, 'source_file' => 'example-plays.csv'])
        ->and($counts[0]->period_start->toDateString())->toBe('2026-02-10')
        ->and($counts[0]->period_end->toDateString())->toBe('2026-02-10')
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
        'file' => playCountFile("track,date,dsp,plays\nExample Track,2026-01-15,Spotify,7\nExample Track,2026-01-15,Example Jukebox,3\nExample Track,2026-01-15,qqmusic,4\n"),
    ])->assertSessionHasNoErrors();

    expect(PlayCount::query()->withoutGlobalScope('label')->where('label_id', $label->id)->orderBy('plays')->get()->map(fn (PlayCount $count): string => "{$count->platform} {$count->plays}")->all())->toBe(['unknown 3', 'tencent 4', 'spotify 7', 'spotify 20', 'tidal 30'])
        ->and($february->fresh())->not->toBeNull()
        ->and($tidal->fresh())->not->toBeNull()
        ->and($other->fresh())->not->toBeNull();
});

test('a file that names neither platform nor dates is the total up to today and replaces what was counted before', function () {
    $this->travelTo('2026-02-10 12:00:00');
    $label = Label::factory()->create();
    $track = Track::factory()->create(['label_id' => $label->id, 'name' => 'Example Track', 'isrc' => 'ZZTST2600001']);
    $shared = ['label_id' => $label->id, 'track_id' => $track->id, 'track_name' => 'Example Track'];
    PlayCount::factory()->create([...$shared, 'platform' => 'unknown', 'plays' => 10, 'period_start' => '2025-06-01', 'period_end' => '2025-06-30']);
    $spotify = PlayCount::factory()->create([...$shared, 'platform' => 'spotify', 'plays' => 20, 'period_start' => '2025-06-01', 'period_end' => '2025-06-30']);

    // Two columns share a header, as in a distributor's export: the later one names the track.
    $this->actingAs(labelAdmin($label))->post(route('play-counts.store'), [
        'file' => playCountFile("EAN;Title;ISRC;Title;Country;Streams\n4000000000001;Example Release;ZZTST2600001;Example Track;DE;5\n4000000000001;Example Release;ZZTST2600001;Example Track;GB;2\n"),
    ])->assertSessionHasNoErrors();

    $counts = PlayCount::query()->withoutGlobalScope('label')->where('label_id', $label->id)->where('platform', 'unknown')->orderBy('id')->get();

    expect($counts->pluck('plays')->all())->toBe([5, 2])
        ->and($counts[0])->toMatchArray(['track_id' => $track->id, 'track_name' => 'Example Track'])
        ->and($counts[0]->period_start->toDateString())->toBe('2026-02-10')
        ->and($spotify->fresh())->not->toBeNull();

    $this->get(route('play-counts.index'))->assertInertia(fn (Assert $page) => $page
        ->where('files.0.platforms', [['key' => 'unknown', 'name' => 'Not named']]));
});

test('a file that cannot be read as play counts is refused and nothing is stored', function (string $content, array $fields, string $message) {
    $label = Label::factory()->create();
    Track::factory()->create(['label_id' => $label->id, 'name' => 'Example Track']);

    $this->actingAs(labelAdmin($label))->post(route('play-counts.store'), ['file' => playCountFile($content), ...$fields])
        ->assertSessionHasErrors(['file' => $message]);

    expect(PlayCount::query()->withoutGlobalScope('label')->count())->toBe(0);
})->with([
    'no play count column' => ["song,revenue\nExample Track,5\n", [], 'The file needs a column with the play count, called "plays" or "streams", and one naming the track, called "track", "song" or "isrc". Found: song, revenue.'],
    'second line breaks' => ["song,streams,date\nExample Track,5,2026-01-01\nExample Track,5,01/02/2026\n", [], 'Line 3: The date "01/02/2026" in "date" can be read two ways. Use the format 2026-03-31.'],
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

test('the page links to the Zebralution export from a week before the last counted day until yesterday', function () {
    $this->travelTo('2026-02-10 12:00:00');
    $label = Label::factory()->create();
    $admin = labelAdmin($label);
    $url = fn (string $from): string => "https://musicanalytics.zebralution.tools/maapidata/Export?startdate={$from}&enddate=2026-02-09&resulttype=streams&groupby_export=track%2Cdsp%2Cdate&fields=streams";

    $this->actingAs($admin)->get(route('play-counts.index'))->assertInertia(fn (Assert $page) => $page->where('zebralutionExport', $url('2025-02-10')));

    PlayCount::factory()->create(['label_id' => $label->id, 'period_start' => '2026-01-20', 'period_end' => '2026-01-20']);
    PlayCount::factory()->create(['period_start' => '2026-02-08', 'period_end' => '2026-02-08']);

    $this->actingAs($admin)->get(route('play-counts.index'))->assertInertia(fn (Assert $page) => $page->where('zebralutionExport', $url('2026-01-13')));
});

test('the page reports the counted plays by platform and month, against the statements, and by release and track', function () {
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id, 'name' => 'Example Release']);
    $track = Track::factory()->create(['label_id' => $label->id, 'release_id' => $release->id, 'name' => 'Example Track']);
    $other = Track::factory()->create(['label_id' => $label->id, 'release_id' => $release->id, 'name' => 'Sample Track']);
    $count = fn (Track $track, string $platform, string $day, int $plays) => PlayCount::factory()->create([
        'label_id' => $label->id, 'track_id' => $track->id, 'platform' => $platform, 'period_start' => $day, 'period_end' => $day, 'plays' => $plays,
    ]);
    $count($track, 'spotify', '2026-01-10', 100);
    $count($track, 'spotify', '2026-03-05', 50);
    $count($other, 'spotify', '2026-03-06', 30);
    $count($other, 'deezer', '2026-02-01', 20);
    PlayCount::factory()->create(['platform' => 'spotify', 'plays' => 999, 'period_start' => '2026-03-01', 'period_end' => '2026-03-01']);
    Transaction::factory()->create([
        'label_id' => $label->id, 'type' => 'income', 'retailer' => 'Spotify', 'format' => 'Stream', 'quantity' => 90,
        'total' => '1', 'converted_total' => '1', 'timestamp' => '2026-01-31',
    ]);

    $this->actingAs(labelAdmin($label))->get(route('play-counts.index'))->assertInertia(fn (Assert $page) => $page
        ->where('report.months', ['2026-01', '2026-02', '2026-03'])
        ->has('report.platforms', 2)
        ->where('report.platforms.0.key', 'spotify')
        ->where('report.platforms.0.plays', 180)
        ->where('report.platforms.0.series.streams', fn ($series): bool => collect($series)->map(fn ($plays): int => (int) $plays)->all() === [100, 0, 80])
        ->where('report.platforms.0.reported_streams', fn ($streams): bool => (int) $streams === 90)
        ->where('report.platforms.1.key', 'deezer')
        ->has('releases.rows', 1)
        ->where('releases.rows.0.label', 'Example Release')
        ->where('releases.rows.0.total', fn ($plays): bool => (int) $plays === 200)
        ->where('tracks.rows.0.label', 'Example Track')
        ->where('tracks.rows.0.total', fn ($plays): bool => (int) $plays === 150)
        ->where('tracks.rows.0.recent', fn ($plays): bool => (int) $plays === 150)
        ->where('tracks.rows.1.label', 'Sample Track'));
});
