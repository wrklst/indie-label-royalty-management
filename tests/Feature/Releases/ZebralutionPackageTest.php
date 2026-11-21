<?php

use App\Models\Artist;
use App\Models\Label;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\Track;
use App\Services\Releases\ZebralutionSheet;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->label = Label::factory()->create([
        'name' => 'Example Records',
        'delivery_settings' => ['zebralution' => ['number' => '1100a1', 'origin_country' => 'DE', 'price_group' => 25, 'work_rights' => 'licensed to distributor by label']],
    ]);
    $this->artist = Artist::factory()->create(['label_id' => $this->label->id, 'name' => 'Example Artist']);
    $this->release = Release::factory()->create([
        'label_id' => $this->label->id, 'artist_id' => $this->artist->id, 'name' => 'Example Release', 'catalog_number' => 'EX001',
        'upc' => '828768767023', 'release_type' => 'ep', 'released_on' => '2026-12-04', 'original_released_on' => '2026-11-27',
        'genre' => 'dub techno', 'subgenre' => '0104080200', 'language' => 'en', 'c_line' => '© 2026 Example Records',
        'p_line' => '(P) 2026 Example Records', 'ai_artwork' => 'partly', 'blocked_territories' => ['RU', 'BY'],
    ]);
    $this->actingAs(labelAdmin($this->label));
});

/**
 * A track that says everything Zebralution asks of one.
 */
function zebralutionTrack(Release $release, array $attributes = []): Track
{
    return Track::factory()->create([
        'label_id' => $release->label_id, 'release_id' => $release->id, 'artist_id' => $release->artist_id, 'isrc' => 'DEA1B2600001',
        'track_number' => 1, 'duration' => 253, 'language' => 'zxx', 'ai_recording' => 'none', 'ai_composition' => 'none', 'ai_lyrics' => 'none',
        ...$attributes,
    ]);
}

function zebralutionFile(Release $release, string $name, array $attributes = [], ?string $contents = null): ReleaseFile
{
    $file = ReleaseFile::factory()->create([
        'label_id' => $release->label_id, 'release_id' => $release->id, 'kind' => 'audio', 'mime_type' => 'audio/wav',
        'original_name' => $name, 'path' => "releases/{$release->label_id}/{$release->id}/".fake()->uuid(), ...$attributes,
    ]);
    Storage::disk('local')->put($file->path, $contents ?? "contents of {$name}");

    return $file;
}

/**
 * A cover as a JPEG of the given size.
 */
function zebralutionCover(Release $release, int $width = 3000, int $height = 3000, string $name = 'front.jpg'): ReleaseFile
{
    ob_start();
    imagejpeg(imagecreate($width, $height), null, 10);

    return zebralutionFile($release, $name, ['kind' => 'cover', 'mime_type' => 'image/jpeg'], (string) ob_get_clean());
}

/**
 * The lines of the workbook, by line number, each as a list of its cells.
 *
 * @return array<int, list<string>>
 */
function zebralutionLines(string $workbook): array
{
    $path = tempnam(sys_get_temp_dir(), 'workbook');
    file_put_contents($path, $workbook);
    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = simplexml_load_string((string) $zip->getFromName('xl/worksheets/sheet1.xml'));
    $zip->close();
    unlink($path);
    $lines = [];

    foreach ($sheet->sheetData->row as $row) {
        foreach ($row->c as $cell) {
            $lines[(int) $row['r']][] = (string) $cell->is->t;
        }
    }

    return $lines;
}

test('the Zebralution ZIP holds the sheet, the cover and the masters named for the FTP server', function () {
    $guest = Artist::factory()->create(['label_id' => $this->label->id, 'name' => 'Example Guest']);
    $second = zebralutionTrack($this->release, [
        'name' => 'Größe: Zwei?', 'version' => 'Live Edit', 'track_number' => 2, 'isrc' => 'DE-A1B-26-00002', 'duration' => 3725, 'language' => null,
        'explicit' => 'clean', 'ai_recording' => 'partly', 'ai_lyrics' => 'all', 'preview_start' => 30, 'iswc' => 'T8029441644',
        'credits' => ['lyricists' => ['First Writer', 'Second Writer'], 'composers' => ['First Writer'], 'remixers' => ['Example Remixer'], 'publishers' => ['Example Publishing']],
    ]);
    $second->artists()->attach($guest->id, ['role' => 'featuring', 'position' => 0]);
    $first = zebralutionTrack($this->release, ['name' => 'Opening']);
    zebralutionCover($this->release);
    zebralutionFile($this->release, 'two.wav', ['track_id' => $second->id]);
    zebralutionFile($this->release, 'one.WAV', ['track_id' => $first->id]);

    $response = $this->get(route('releases.zebralution-package', $this->release))->assertOk()->assertHeader('Content-Type', 'application/zip');

    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $names = array_map(fn (int $index): string => (string) $zip->getNameIndex($index), range(0, $zip->numFiles - 1));
    $lines = zebralutionLines((string) $zip->getFromName('Zebralution_Metadata_0828768767023.xlsx'));

    expect($names)->toBe(['0828768767023.jpg', '0828768767023/1-1 Opening.wav', '0828768767023/1-2 Grosse Zwei (Live Edit).wav', 'Zebralution_Metadata_0828768767023.xlsx'])
        ->and($zip->getFromName('0828768767023/1-1 Opening.wav'))->toBe('contents of one.WAV')
        ->and($response->headers->get('Content-Disposition'))->toContain('filename="Example Release - Zebralution.zip"')
        ->and(array_keys($lines))->toBe([1, 2, 4, 5, 6, 7])
        ->and($lines[1])->toBe(['Version 2.19Z'])
        ->and($lines[5])->toBe(array_keys(ZebralutionSheet::COLUMNS))
        ->and(array_slice($lines[4], 0, 8))->toBe(['Mandatory', 'Mandatory', 'Mandatory', 'Mandatory', 'Mandatory', 'Mandatory', 'Optional', 'Optional'])
        ->and(array_combine($lines[5], $lines[6]))->toBe([
            'ZebralutionNumber' => '1100a1', 'LabelName' => 'Example Records', 'AlbumEAN_UPC' => '0828768767023', 'AlbumArtist' => 'Example Artist',
            'AlbumArtistAdd' => '131: Example Artist', 'AlbumTitle' => 'Example Release', 'AlbumVersion' => '-', 'AlbumArtistURL' => '-',
            'AlbumLabelOrderNumber' => 'EX001', 'AlbumMediaType' => '7', 'AsisTypes' => '-', 'AlbumMfiTProvider' => '-', 'TrackCount' => '2',
            'AlbumReleaseDate' => '20261127', 'AlbumDigitalReleaseDate' => '20261204', 'AlbumPriceGroup' => '25', 'AlbumGenre' => '0104030020',
            'AlbumGenre2' => '0104080200', 'DistributionTerritory' => 'WW -RU -BY', 'AlbumPreorderReleaseDate' => '-', 'AlbumPreorderWeeks' => '-',
            'AlbumPreorderPreview' => '-', 'AlbumPreorderPriceGroup' => '-', 'DontDeliverToShop' => '-', 'OnlyDeliverToShop' => '-',
            'AllowSubscriptionService' => 'y', 'AlbumLanguage' => 'en', 'AlbumCopyrightInfo' => '2026 Example Records', 'ContainsAI_AlbumArtwork' => 'partly',
            'AlbumProductionInfo' => '2026 Example Records', 'BookletIncluded' => 'n', 'LinerNotes' => '-', 'ISRC' => 'DEA1B2600001', 'RelatedISRC' => '-',
            'TrackArtist' => 'Example Artist', 'TrackArtistAdd' => '131: Example Artist', 'TrackTitle' => 'Opening', 'TrackVersion' => '-',
            'TrackDuration' => '00:04:13', 'TrackPrelistenStartSecond' => '-', 'TrackNumber' => '1', 'SetNumber' => '1', 'SetName' => '-',
            'TrackLanguage' => 'zxx', 'TrackLive' => 'n', 'TrackType' => 'audio', 'TrackSaleAllowed' => 'y', 'TrackPriceGroup' => '25',
            'TrackReleaseDate' => '20261127', 'TrackDigitalReleaseDate' => '20261204', 'TrackPreorderType' => '-', 'InstantGratStartDate' => '-',
            'TrackGenre' => '0104030020', 'TrackGenre2' => '-', 'CopyrightInfo' => '2026 Example Records', 'ProductionInfo' => '2026 Example Records',
            'TrackExplicitLyrics' => 'n', 'OriginCountry' => 'DE', 'TrackLyricist' => '-', 'TrackComposer' => 'n/a', 'ISWC' => '-', 'TrackArranger' => '-',
            'TrackProducer' => '-', 'TrackPublisher' => '-', 'IsPublicDomain' => 'n', 'IsFunctionalContent' => 'n', 'ContainsAI' => 'none',
            'ContainsAI_Composition' => 'none', 'ContainsAI_Lyrics' => 'none', 'ContainsAI_VideoVisual' => 'none',
            'MusicalWorkRights' => 'licensed to distributor by label', 'MusicPercentage' => '100',
        ])
        ->and(array_intersect_key(array_combine($lines[5], $lines[7]), array_flip([
            'ISRC', 'TrackArtistAdd', 'TrackTitle', 'TrackVersion', 'TrackDuration', 'TrackPrelistenStartSecond', 'TrackNumber', 'TrackLanguage', 'TrackLive',
            'TrackExplicitLyrics', 'TrackLyricist', 'TrackComposer', 'ISWC', 'TrackPublisher', 'ContainsAI', 'ContainsAI_Composition', 'ContainsAI_Lyrics',
        ])))->toBe([
            'ISRC' => 'DEA1B2600002', 'TrackArtistAdd' => '131: Example Artist; 501: Example Guest; 506: Example Remixer', 'TrackTitle' => 'Größe: Zwei?',
            'TrackVersion' => 'Live Edit', 'TrackDuration' => '01:02:05', 'TrackPrelistenStartSecond' => '30', 'TrackNumber' => '2', 'TrackLanguage' => 'en',
            'TrackLive' => 'y', 'TrackExplicitLyrics' => 'cleaned', 'TrackLyricist' => 'First Writer, Second Writer', 'TrackComposer' => 'First Writer',
            'ISWC' => 'T8029441644', 'TrackPublisher' => 'Example Publishing', 'ContainsAI' => 'partly', 'ContainsAI_Composition' => 'none', 'ContainsAI_Lyrics' => 'all',
        ]);
});

test('a compilation goes out under Various Artists and as an album', function () {
    $this->release->update(['release_type' => 'compilation']);
    $track = zebralutionTrack($this->release);
    zebralutionCover($this->release);
    zebralutionFile($this->release, 'one.wav', ['track_id' => $track->id]);

    $response = $this->get(route('releases.zebralution-package', $this->release))->assertOk();

    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $lines = zebralutionLines((string) $zip->getFromName('Zebralution_Metadata_0828768767023.xlsx'));

    expect(array_intersect_key(array_combine($lines[5], $lines[6]), array_flip(['AlbumArtist', 'AlbumArtistAdd', 'AlbumMediaType', 'TrackArtist'])))
        ->toBe(['AlbumArtist' => 'Various Artists', 'AlbumArtistAdd' => '131: Various Artists', 'AlbumMediaType' => '8', 'TrackArtist' => 'Example Artist']);
});

test('the Zebralution ZIP is refused with everything Zebralution would send the release back for', function () {
    $this->label->update(['delivery_settings' => null]);
    $this->release->update([
        'artist_id' => null, 'upc' => null, 'released_on' => null, 'genre' => null, 'subgenre' => 'Deep Space', 'language' => null,
        'c_line' => 'Example Records', 'p_line' => null, 'ai_artwork' => null,
    ]);
    $track = zebralutionTrack($this->release, [
        'name' => 'Opening', 'artist_id' => null, 'isrc' => null, 'duration' => null, 'language' => null, 'genre' => 'Space Rock Opera',
        'ai_recording' => null, 'ai_composition' => 'none', 'ai_lyrics' => null,
    ]);
    zebralutionFile($this->release, 'one.flac', ['track_id' => $track->id]);

    $this->get(route('releases.zebralution-package', $this->release))
        ->assertRedirect(route('releases.show', $this->release))
        ->assertSessionHasErrors(['zebralution_package' => implode("\n", [
            'The release has no cover.',
            'Track 1 “Opening” only has audio Zebralution does not accept (“one.flac”); it takes WAV with 16 bit, 44.1 kHz, stereo.',
            'The Zebralution number of the label is missing. Enter it under Settings → Connections.',
            'The country the music comes from is missing. Enter it under Settings → Connections.',
            'The release has no UPC / EAN.',
            'The release has no main artist.',
            'The release has no release date.',
            'The release has no language. Enter the language of its title as a code such as en or de.',
            'The © line of the release must start with the year, as in “2026 Example Label”.',
            'The ℗ line of the release must start with the year, as in “2026 Example Label”.',
            'The release does not say whether its artwork is AI-generated.',
            'The release has no genre.',
            "“Deep Space” is not one of Zebralution's genres. Pick one of the genres the field suggests.",
            'Track 1 “Opening” has no ISRC.',
            'Track 1 “Opening” has no duration.',
            'Track 1 “Opening” does not say whether its recording, lyrics are AI-generated.',
            "“Space Rock Opera”, the genre of Track 1 “Opening”, is not one of Zebralution's genres.",
            'Track 1 “Opening” has no lyricist. Enter one, or set its language to zxx if it is instrumental.',
        ])]);
});

test('the Zebralution ZIP is refused for a cover Zebralution does not take', function (int $width, int $height, string $name, string $message) {
    $track = zebralutionTrack($this->release);
    zebralutionCover($this->release, $width, $height, $name);
    zebralutionFile($this->release, 'one.wav', ['track_id' => $track->id]);

    $this->get(route('releases.zebralution-package', $this->release))
        ->assertRedirect(route('releases.show', $this->release))
        ->assertSessionHasErrors(['zebralution_package' => $message]);
})->with([
    'too small' => [2999, 2999, 'front.jpg', 'The cover is 2999 × 2999 pixels; Zebralution takes square covers of at least 3000 × 3000.'],
    'not square' => [3200, 3000, 'front.jpg', 'The cover is 3200 × 3000 pixels; Zebralution takes square covers of at least 3000 × 3000.'],
    'not a JPEG' => [3000, 3000, 'front.png', 'The cover “front.png” is not a JPEG, which is the only format Zebralution takes.'],
]);

test('the Zebralution ZIP of another label cannot be downloaded', function () {
    $this->get(route('releases.zebralution-package', Release::factory()->create()))->assertNotFound();
});
