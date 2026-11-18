<?php

use App\Models\Artist;
use App\Models\Label;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\Track;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->label = Label::factory()->create(['name' => 'Example Records']);
    $this->actingAs(labelAdmin($this->label));
});

/**
 * @return list<array<string, string>>
 */
function csvRows(string $csv): array
{
    $lines = array_map(fn (string $line): array => str_getcsv($line, ',', '"', ''), array_filter(explode("\n", trim($csv))));
    $header = array_shift($lines);

    return array_map(fn (array $line): array => array_combine($header, $line), $lines);
}

test('a release is saved with its delivery details and artist credits', function () {
    $main = Artist::factory()->create(['label_id' => $this->label->id, 'name' => 'Example Artist']);
    $guest = Artist::factory()->create(['label_id' => $this->label->id, 'name' => 'Example Guest']);

    $this->post(route('releases.store'), [
        'name' => 'Example Release', 'artist_id' => $main->id, 'status' => 'in_progress', 'release_type' => 'ep', 'version' => 'The Remixes',
        'genre' => 'Electronic', 'language' => 'zxx', 'explicit' => 'clean', 'p_line' => '2026 Example Records', 'preorder_on' => '2026-05-01',
        'press_release' => "First paragraph.\n\nSecond paragraph.", 'blocked_services' => 'Example Stream, Other Store', 'blocked_territories' => 'ru, il',
        'artists' => [['artist_id' => $guest->id, 'role' => 'featuring']],
    ])->assertSessionHasNoErrors();

    $release = Release::query()->withoutGlobalScopes()->sole();

    expect($release->status->value)->toBe('in_progress')
        ->and($release->release_type?->value)->toBe('ep')
        ->and($release->blocked_services)->toBe(['Example Stream', 'Other Store'])
        ->and($release->blocked_territories)->toBe(['RU', 'IL'])
        ->and($release->preorder_on?->toDateString())->toBe('2026-05-01')
        ->and($release->artists()->sole()->pivot->role)->toBe('featuring');

    $this->get(route('releases.show', $release))
        ->assertInertia(fn ($page) => $page->where('release.artist', 'Example Artist feat. Example Guest')->where('release.release_type', 'EP'));
});

test('release details are validated', function (array $input, string $field) {
    $this->post(route('releases.store'), ['name' => 'Example Release', ...$input])->assertSessionHasErrors($field);
})->with([
    'an unknown status' => [['status' => 'shipped'], 'status'],
    'an unknown format' => [['release_type' => 'mixtape'], 'release_type'],
    'a territory that is not a country code' => [['blocked_territories' => 'Russia'], 'blocked_territories.0'],
    'a link that is not a web address' => [['files_url' => 'javascript:alert(1)'], 'files_url'],
    'an artist of another label' => [['artists' => [['artist_id' => 999999, 'role' => 'featuring']]], 'artists.0.artist_id'],
]);

test('saving a release without touching credits keeps them and an empty list clears them', function () {
    $release = Release::factory()->create(['label_id' => $this->label->id]);
    $release->artists()->attach(Artist::factory()->create(['label_id' => $this->label->id]), ['role' => 'remixer', 'position' => 0]);

    $this->put(route('releases.update', $release->id), ['name' => 'Renamed'])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('artist_release', 1);

    $this->put(route('releases.update', $release->id), ['name' => 'Renamed', 'artists' => []])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('artist_release', 0);
});

test('track details and credits are saved from the track dialog', function () {
    $release = Release::factory()->create(['label_id' => $this->label->id]);
    $track = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $release->id]);
    $remixer = Artist::factory()->create(['label_id' => $this->label->id]);

    $this->put(route('tracks.update', $track->id), [
        'name' => 'Example Track', 'version' => 'Club Mix', 'bpm' => '128.5', 'musical_key' => 'F minor', 'explicit' => 'explicit', 'disc_number' => 1,
        'credits' => ['composers' => 'First Writer, Second Writer', 'publishers' => '', 'producers' => 'Example Producer'],
        'artists' => [['artist_id' => $remixer->id, 'role' => 'remixer']],
    ])->assertSessionHasNoErrors();

    $track->refresh();

    expect($track->version)->toBe('Club Mix')
        ->and((float) $track->bpm)->toBe(128.5)
        ->and($track->credits)->toBe(['composers' => ['First Writer', 'Second Writer'], 'producers' => ['Example Producer']])
        ->and($track->artists()->sole()->id)->toBe($remixer->id);
});

test('track details are validated', function (array $input, string $field) {
    $track = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => Release::factory()->create(['label_id' => $this->label->id])]);

    $this->put(route('tracks.update', $track->id), ['name' => 'Example Track', ...$input])->assertSessionHasErrors($field);
})->with([
    'an impossible tempo' => [['bpm' => '4000'], 'bpm'],
    'an unknown credit role' => [['credits' => ['dancers' => 'Somebody']], 'credits'],
    'an unknown artist role' => [['artists' => [['artist_id' => 1, 'role' => 'mascot']]], 'artists.0.role'],
]);

test('the release list can be narrowed by status', function () {
    Release::factory()->create(['label_id' => $this->label->id, 'name' => 'Planned', 'status' => 'todo']);
    Release::factory()->create(['label_id' => $this->label->id, 'name' => 'Out', 'status' => 'done']);

    $this->get(route('releases.index', ['status' => 'todo']))
        ->assertInertia(fn ($page) => $page->has('releases.data', 1)->where('releases.data.0.name', 'Planned')->where('filters.status', 'todo'));
});

test('the metadata sheet has one line per track with inherited values filled in', function () {
    $artist = Artist::factory()->create(['label_id' => $this->label->id, 'name' => 'Example Artist']);
    $remixer = Artist::factory()->create(['label_id' => $this->label->id, 'name' => 'Example Remixer']);
    $release = Release::factory()->create([
        'label_id' => $this->label->id, 'artist_id' => $artist->id, 'name' => 'Example Release', 'catalog_number' => 'EX001', 'upc' => '4000000000017',
        'release_type' => 'single', 'released_on' => '2026-03-31', 'genre' => 'Electronic', 'p_line' => '2026 Example Records', 'blocked_territories' => ['RU'],
    ]);
    Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $release->id, 'name' => 'B Side', 'track_number' => 2, 'isrc' => 'ZZTST2600002', 'duration' => 65]);
    $first = Track::factory()->create([
        'label_id' => $this->label->id, 'release_id' => $release->id, 'name' => 'A Side', 'track_number' => 1, 'isrc' => 'ZZTST2600001', 'duration' => 311,
        'version' => 'Remix', 'genre' => 'House', 'credits' => ['composers' => ['First Writer', 'Second Writer']],
    ]);
    $first->artists()->attach($remixer, ['role' => 'remixer', 'position' => 0]);
    Release::factory()->create(['label_id' => Label::factory(), 'name' => 'Other Label Release']);

    $rows = csvRows($this->get(route('releases.metadata'))->assertOk()->streamedContent());

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchArray([
            'catalog_number' => 'EX001', 'upc' => '4000000000017', 'release_title' => 'Example Release', 'release_artist' => 'Example Artist',
            'release_type' => 'Single', 'release_date' => '2026-03-31', 'label' => 'Example Records', 'blocked_territories' => 'RU',
            'track_number' => '1', 'track_title' => 'A Side', 'track_version' => 'Remix', 'track_artist' => 'Example Artist', 'remixers' => 'Example Remixer',
            'isrc' => 'ZZTST2600001', 'duration' => '5:11', 'duration_seconds' => '311', 'track_genre' => 'House', 'composers' => 'First Writer, Second Writer',
            'track_p_line' => '2026 Example Records',
        ])
        ->and($rows[1]['track_title'])->toBe('B Side')
        ->and($rows[1]['track_genre'])->toBe('Electronic');
});

test('the metadata sheet can be limited to one release and hides other labels', function () {
    $mine = Release::factory()->create(['label_id' => $this->label->id, 'name' => '=Formula Release']);
    $foreign = Release::factory()->create(['label_id' => Label::factory()]);

    $rows = csvRows($this->get(route('releases.metadata', ['release' => $mine->id]))->streamedContent());

    expect($rows)->toHaveCount(1)->and($rows[0]['release_title'])->toBe("'=Formula Release");

    $this->get(route('releases.metadata', ['release' => $foreign->id]))->assertNotFound();
});

test('the delivery package bundles cover, named audio, the sheet and the label copy', function () {
    Storage::fake('local');
    $artist = Artist::factory()->create(['label_id' => $this->label->id, 'name' => 'Example Artist']);
    $release = Release::factory()->create(['label_id' => $this->label->id, 'artist_id' => $artist->id, 'name' => 'Example: Release?', 'catalog_number' => 'EX001', 'press_release' => 'Out now.']);
    $track = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $release->id, 'name' => 'A Side', 'track_number' => 1, 'isrc' => 'ZZTST2600001']);

    foreach ([
        ['kind' => 'cover', 'original_name' => 'front.JPG', 'mime_type' => 'image/jpeg', 'path' => 'releases/p/1.jpg'],
        ['kind' => 'audio', 'original_name' => 'bounce.wav', 'mime_type' => 'audio/wav', 'path' => 'releases/p/2.wav', 'track_id' => $track->id],
        ['kind' => 'audio', 'original_name' => 'bounce.wav', 'mime_type' => 'audio/wav', 'path' => 'releases/p/3.wav', 'track_id' => $track->id],
        ['kind' => 'document', 'original_name' => 'One Sheet.pdf', 'path' => 'releases/p/4.pdf'],
    ] as $attributes) {
        Storage::disk('local')->put($attributes['path'], 'contents of '.$attributes['original_name']);
        ReleaseFile::factory()->create(['label_id' => $this->label->id, 'release_id' => $release->id, ...$attributes]);
    }

    $response = $this->get(route('releases.package', $release))->assertOk();
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $names = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $names[] = $zip->getNameIndex($index);
    }

    $folder = 'EX001 - Example Artist - Example Release';

    expect($names)->toEqualCanonicalizing([
        "{$folder}/cover.jpg",
        "{$folder}/audio/01 - Example Artist - A Side.wav",
        "{$folder}/audio/01 - Example Artist - A Side (2).wav",
        "{$folder}/documents/One Sheet.pdf",
        "{$folder}/metadata.csv",
        "{$folder}/release.txt",
    ])
        ->and($zip->getFromName("{$folder}/audio/01 - Example Artist - A Side.wav"))->toBe('contents of bounce.wav')
        ->and($zip->getFromName("{$folder}/release.txt"))->toContain('01. Example Artist - A Side  ISRC ZZTST2600001')->toContain('PRESS RELEASE')
        ->and($response->headers->get('Content-Disposition'))->toContain('.zip');
});

test('a package of another label cannot be downloaded', function () {
    $this->get(route('releases.package', Release::factory()->create(['label_id' => Label::factory()])->id))->assertNotFound();
});
