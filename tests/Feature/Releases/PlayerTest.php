<?php

use App\Enums\LabelRole;
use App\Models\Artist;
use App\Models\Label;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->label = Label::factory()->create();
    $this->artist = Artist::factory()->create(['label_id' => $this->label->id, 'name' => 'Example Artist']);
    $this->release = Release::factory()->create(['label_id' => $this->label->id, 'artist_id' => $this->artist->id, 'name' => 'Example Release']);
    $this->actingAs(labelAdmin($this->label));
});

function playerTrack(Release $release, array $attributes = []): Track
{
    return Track::factory()->create(['label_id' => $release->label_id, 'release_id' => $release->id, 'artist_id' => null, ...$attributes]);
}

function playerFile(Release $release, array $attributes = []): ReleaseFile
{
    return ReleaseFile::factory()->create([
        'label_id' => $release->label_id, 'release_id' => $release->id, 'kind' => 'audio', 'mime_type' => 'audio/wav', 'original_name' => 'master.wav', ...$attributes,
    ]);
}

test('the release page gives the player a file, title, artist, release and cover for each track', function () {
    $track = playerTrack($this->release, ['name' => 'Opening', 'track_number' => 1]);
    playerTrack($this->release, ['name' => 'Silent One', 'track_number' => 2]);
    $audio = playerFile($this->release, ['track_id' => $track->id]);
    $cover = playerFile($this->release, ['kind' => 'cover', 'mime_type' => 'image/png', 'original_name' => 'cover.png']);

    $this->get(route('releases.show', $this->release))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('release.id', $this->release->id)
            ->where('release.name', 'Example Release')
            ->where('release.artist', 'Example Artist')
            ->where('release.cover_file_id', $cover->id)
            ->where('release.tracks.0.name', 'Opening')
            ->where('release.tracks.0.audio_file_id', $audio->id)
            ->where('release.tracks.1.name', 'Silent One')
            ->where('release.tracks.1.audio_file_id', null));
});

test('the release list and the artist page give the player each release as an album in play order without silent tracks', function (Closure $url, string $path) {
    $guest = Artist::factory()->create(['label_id' => $this->label->id, 'name' => 'Guest Artist']);
    $second = playerTrack($this->release, ['name' => 'Second', 'track_number' => 2, 'artist_id' => $guest->id, 'version' => 'Club Mix']);
    $first = playerTrack($this->release, ['name' => 'First', 'track_number' => 1]);
    playerTrack($this->release, ['name' => 'Silent One', 'track_number' => 3]);
    $secondAudio = playerFile($this->release, ['track_id' => $second->id]);
    $firstAudio = playerFile($this->release, ['track_id' => $first->id]);
    $cover = playerFile($this->release, ['kind' => 'cover', 'mime_type' => 'image/png', 'original_name' => 'cover.png']);
    $other = Release::factory()->create(['label_id' => $this->label->id, 'artist_id' => $this->artist->id, 'name' => 'Another Release']);
    playerFile($other, ['track_id' => playerTrack($other, ['name' => 'Elsewhere'])->id]);

    $this->get($url($this))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where($path, fn ($releases) => collect($releases)->firstWhere('id', $this->release->id)['player_tracks'] === [
            ['id' => $first->id, 'fileId' => $firstAudio->id, 'title' => 'First', 'artist' => 'Example Artist', 'artistId' => $this->artist->id, 'release' => ['id' => $this->release->id, 'name' => 'Example Release'], 'coverFileId' => $cover->id],
            ['id' => $second->id, 'fileId' => $secondAudio->id, 'title' => 'Second (Club Mix)', 'artist' => 'Guest Artist', 'artistId' => $guest->id, 'release' => ['id' => $this->release->id, 'name' => 'Example Release'], 'coverFileId' => $cover->id],
        ]));
})->with([
    'release list' => [fn ($test) => route('releases.index'), 'releases.data'],
    'artist page' => [fn ($test) => route('artists.show', $test->artist), 'releases'],
]);

test('the tracks list gives the player a file, title, artist, release and cover for each track', function () {
    $guest = Artist::factory()->create(['label_id' => $this->label->id, 'name' => 'Guest Artist']);
    $withAudio = playerTrack($this->release, ['name' => 'Alpha', 'artist_id' => $guest->id, 'version' => 'Club Mix']);
    playerTrack($this->release, ['name' => 'Beta']);
    $audio = playerFile($this->release, ['track_id' => $withAudio->id]);
    playerFile($this->release, ['kind' => 'cover', 'mime_type' => 'image/png']);
    $cover = playerFile($this->release, ['kind' => 'cover', 'mime_type' => 'image/png']);

    $this->get(route('tracks.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tracks.data.0.name', 'Alpha')
            ->where('tracks.data.0.version', 'Club Mix')
            ->where('tracks.data.0.audio_file_id', $audio->id)
            ->where('tracks.data.0.credited_artist', 'Guest Artist')
            ->where('tracks.data.0.cover_file_id', $cover->id)
            ->where('tracks.data.0.release.id', $this->release->id)
            ->where('tracks.data.0.release.name', 'Example Release')
            ->where('tracks.data.1.name', 'Beta')
            ->where('tracks.data.1.audio_file_id', null)
            ->where('tracks.data.1.credited_artist', 'Example Artist'));
});

test('a track gets play buttons for any audio file and prefers one every browser plays', function () {
    $master = playerTrack($this->release, ['name' => 'Alpha']);
    $both = playerTrack($this->release, ['name' => 'Beta']);
    $unconverted = playerFile($this->release, ['track_id' => $master->id, 'mime_type' => 'audio/x-ms-wma', 'original_name' => 'master.wma']);
    playerFile($this->release, ['track_id' => $both->id, 'mime_type' => 'audio/wav']);
    $mp3 = playerFile($this->release, ['track_id' => $both->id, 'mime_type' => 'audio/mpeg', 'original_name' => 'listening copy.mp3']);
    playerFile($this->release, ['track_id' => $both->id, 'kind' => 'document', 'mime_type' => 'application/pdf']);

    $this->get(route('tracks.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('tracks.data.0.audio_file_id', $unconverted->id)
        ->where('tracks.data.1.audio_file_id', $mp3->id));

    $this->get(route('releases.show', $this->release))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('release.tracks', fn ($tracks) => collect($tracks)->pluck('audio_file_id', 'name')->all() === ['Alpha' => $unconverted->id, 'Beta' => $mp3->id]));
});

test('the tracks list does not run more queries when it lists more tracks', function () {
    $addTrack = function (string $name): void {
        $release = Release::factory()->create(['label_id' => $this->label->id, 'artist_id' => $this->artist->id]);
        $track = playerTrack($release, ['name' => $name]);
        playerFile($release, ['track_id' => $track->id]);
        playerFile($release, ['kind' => 'cover', 'mime_type' => 'image/png']);
    };
    $queriesForTheList = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('tracks.index'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $addTrack('Alpha');
    $withOneTrack = $queriesForTheList();

    $addTrack('Beta');
    $addTrack('Gamma');
    $addTrack('Delta');

    expect($queriesForTheList())->toBe($withOneTrack);
});

test('audio of another label never reaches the player', function () {
    $otherLabel = Label::factory()->create();
    $otherRelease = Release::factory()->create(['label_id' => $otherLabel->id, 'name' => 'Foreign Release']);
    $otherTrack = playerTrack($otherRelease, ['name' => 'Foreign Track']);
    $otherFile = playerFile($otherRelease, ['track_id' => $otherTrack->id]);
    $own = playerTrack($this->release, ['name' => 'Alpha']);
    // A file that wrongly points at one of our tracks must not leak either.
    playerFile($otherRelease, ['track_id' => $own->id]);

    $this->get(route('tracks.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('tracks.data', 1)
        ->where('tracks.data.0.name', 'Alpha')
        ->where('tracks.data.0.audio_file_id', null));

    $this->get(route('releases.show', $otherRelease))->assertNotFound();
    $this->get(route('release-files.show', $otherFile))->assertNotFound();
});

test('a payee cannot open the pages or files the player is fed from', function () {
    $track = playerTrack($this->release, ['name' => 'Alpha']);
    $file = playerFile($this->release, ['track_id' => $track->id]);
    $user = User::factory()->create(['current_label_id' => $this->label->id]);
    $this->label->users()->attach($user, ['role' => LabelRole::Payee->value]);

    $this->actingAs($user);

    $this->get(route('tracks.index'))->assertRedirect(route('portal.index'));
    $this->get(route('releases.show', $this->release))->assertRedirect(route('portal.index'));
    $this->get(route('release-files.show', $file))->assertRedirect(route('portal.index'));
});
