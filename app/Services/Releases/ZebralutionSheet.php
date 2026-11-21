<?php

namespace App\Services\Releases;

use App\Enums\ArtistRole;
use App\Enums\ReleaseType;
use App\Models\Artist;
use App\Models\Release;
use App\Models\Track;
use App\Services\Imports\Identifiers;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

/**
 * One release as Zebralution's metadata sheet for music, version 2.19Z
 * (Build 2026): one line per track, the columns in its order and under
 * its names, the values in the formats its "Example" tab asks for.
 *
 * Zebralution reads an empty cell as "same as the cell above", so every
 * cell is filled; what has no value is "-".
 */
class ZebralutionSheet
{
    public const string VERSION = 'Version 2.19Z';

    public const string BUILD = 'Build 2026';

    /**
     * Every column of the sheet in its order, and whether Zebralution demands it.
     *
     * @var array<string, bool>
     */
    public const array COLUMNS = [
        'ZebralutionNumber' => true, 'LabelName' => true, 'AlbumEAN_UPC' => true, 'AlbumArtist' => true, 'AlbumArtistAdd' => true,
        'AlbumTitle' => true, 'AlbumVersion' => false, 'AlbumArtistURL' => false, 'AlbumLabelOrderNumber' => true, 'AlbumMediaType' => true,
        'AsisTypes' => false, 'AlbumMfiTProvider' => false, 'TrackCount' => true, 'AlbumReleaseDate' => true, 'AlbumDigitalReleaseDate' => true,
        'AlbumPriceGroup' => true, 'AlbumGenre' => true, 'AlbumGenre2' => false, 'DistributionTerritory' => true, 'AlbumPreorderReleaseDate' => false,
        'AlbumPreorderWeeks' => false, 'AlbumPreorderPreview' => false, 'AlbumPreorderPriceGroup' => false, 'DontDeliverToShop' => false,
        'OnlyDeliverToShop' => false, 'AllowSubscriptionService' => true, 'AlbumLanguage' => true, 'AlbumCopyrightInfo' => true,
        'ContainsAI_AlbumArtwork' => true, 'AlbumProductionInfo' => true, 'BookletIncluded' => false, 'LinerNotes' => false, 'ISRC' => true,
        'RelatedISRC' => false, 'TrackArtist' => true, 'TrackArtistAdd' => true, 'TrackTitle' => true, 'TrackVersion' => false,
        'TrackDuration' => true, 'TrackPrelistenStartSecond' => false, 'TrackNumber' => true, 'SetNumber' => true, 'SetName' => false,
        'TrackLanguage' => true, 'TrackLive' => true, 'TrackType' => false, 'TrackSaleAllowed' => true, 'TrackPriceGroup' => true,
        'TrackReleaseDate' => true, 'TrackDigitalReleaseDate' => false, 'TrackPreorderType' => false, 'InstantGratStartDate' => false,
        'TrackGenre' => true, 'TrackGenre2' => false, 'CopyrightInfo' => true, 'ProductionInfo' => true, 'TrackExplicitLyrics' => true,
        'OriginCountry' => true, 'TrackLyricist' => true, 'TrackComposer' => true, 'ISWC' => false, 'TrackArranger' => false,
        'TrackProducer' => false, 'TrackPublisher' => true, 'IsPublicDomain' => false, 'IsFunctionalContent' => false, 'ContainsAI' => true,
        'ContainsAI_Composition' => true, 'ContainsAI_Lyrics' => true, 'ContainsAI_VideoVisual' => true, 'MusicalWorkRights' => true,
        'MusicPercentage' => false,
    ];

    /** Who may license the composition and lyrics, in Zebralution's words; the first is the default. */
    public const array WORK_RIGHTS = ['administered by collecting society', 'licensed to distributor by label', 'copyright expired', 'no music included'];

    /** Price groups that exist for tracks, EPs and albums alike: back, mid, standard, standard plus, high, high plus. */
    public const array PRICE_GROUPS = [5, 10, 20, 25, 30, 35];

    /** The roles of the "ArtistAdd" tab an artist credit can have here. Producers go into their own column. */
    private const array ROLE_CODES = [
        'primary' => 131, 'featuring' => 501, 'with' => 502, 'versus' => 503, 'meets' => 504, 'presents' => 505, 'remixer' => 506,
    ];

    private const string INSTRUMENTAL = 'zxx';

    /**
     * What Zebralution demands and the release does not say yet, as
     * sentences for the user. Empty when the sheet is complete.
     *
     * @param  Collection<int, Track>  $tracks
     * @return list<string>
     */
    public function problems(Release $release, Collection $tracks): array
    {
        $problems = [];
        $label = $release->label;

        if (blank($label->deliverySetting('zebralution.number'))) {
            $problems[] = 'The Zebralution number of the label is missing. Enter it under Settings → Connections.';
        }

        if (blank($label->deliverySetting('zebralution.origin_country'))) {
            $problems[] = 'The country the music comes from is missing. Enter it under Settings → Connections.';
        }

        foreach ([
            'The release has no UPC / EAN.' => Identifiers::upc($release->upc) === null,
            'The release has no main artist.' => $release->release_type !== ReleaseType::Compilation && ArtistCredits::names($release, ArtistRole::Primary) === [],
            'The release has no release date.' => $release->released_on === null,
            'The release has no language. Enter the language of its title as a code such as en or de.' => blank($release->language),
            'The © line of the release must start with the year, as in “2026 Example Label”.' => ! $this->startsWithYear($release->c_line),
            'The ℗ line of the release must start with the year, as in “2026 Example Label”.' => ! $this->startsWithYear($release->p_line),
            'The release does not say whether its artwork is AI-generated.' => $release->ai_artwork === null,
        ] as $problem => $applies) {
            if ($applies) {
                $problems[] = $problem;
            }
        }

        if (blank($release->genre)) {
            $problems[] = 'The release has no genre.';
        }

        foreach (array_filter([$release->genre, $release->subgenre]) as $genre) {
            if (ZebralutionGenres::code($genre) === null) {
                $problems[] = "“{$genre}” is not one of Zebralution's genres. Pick one of the genres the field suggests.";
            }
        }

        foreach ($tracks as $track) {
            $name = ($track->track_number !== null ? "Track {$track->track_number}" : 'The track')." “{$track->name}”";
            $undeclared = array_keys(array_filter(['recording' => $track->ai_recording, 'composition' => $track->ai_composition, 'lyrics' => $track->ai_lyrics], is_null(...)));

            foreach ([
                "{$name} has no ISRC." => Identifiers::isrc($track->isrc) === null,
                "{$name} has no duration." => $track->duration === null || $track->duration < 1,
                "{$name} does not say whether its ".implode(', ', $undeclared).($undeclared === [] ? '' : (count($undeclared) > 1 ? ' are' : ' is')).' AI-generated.' => $undeclared !== [],
                "“{$track->genre}”, the genre of {$name}, is not one of Zebralution's genres." => filled($track->genre) && ZebralutionGenres::code($track->genre) === null,
                "{$name} has no lyricist. Enter one, or set its language to zxx if it is instrumental." => ($track->credits['lyricists'] ?? []) === [] && $this->language($release, $track) !== self::INSTRUMENTAL,
            ] as $problem => $applies) {
                if ($applies) {
                    $problems[] = $problem;
                }
            }
        }

        return $problems;
    }

    /**
     * One line per track, keyed by column.
     *
     * @param  Collection<int, Track>  $tracks  in track order
     * @param  array<int, int>  $numbers  the number of each track on its disc, by track id
     * @return list<array<string, string>>
     */
    public function rows(Release $release, Collection $tracks, array $numbers): array
    {
        $label = $release->label;
        $ean = self::ean($release);
        $compilation = $release->release_type === ReleaseType::Compilation;
        $releaseDate = $this->date($release->original_released_on ?? $release->released_on);
        $digitalDate = $this->date($release->released_on);
        $priceGroup = (string) $label->deliverySetting('zebralution.price_group', 20);
        $genre = ZebralutionGenres::code($release->genre);

        $album = [
            'ZebralutionNumber' => (string) $label->deliverySetting('zebralution.number'),
            'LabelName' => $label->name,
            'AlbumEAN_UPC' => $ean,
            'AlbumArtist' => $compilation ? 'Various Artists' : implode(', ', ArtistCredits::names($release, ArtistRole::Primary)),
            'AlbumArtistAdd' => $compilation ? '131: Various Artists' : $this->credits($release),
            'AlbumTitle' => $release->name,
            'AlbumVersion' => $release->version,
            'AlbumLabelOrderNumber' => $release->catalog_number ?: $ean,
            'AlbumMediaType' => $this->mediaType($release, $tracks->count()),
            'TrackCount' => (string) $tracks->count(),
            'AlbumReleaseDate' => $releaseDate,
            'AlbumDigitalReleaseDate' => $digitalDate,
            'AlbumPriceGroup' => $priceGroup,
            'AlbumGenre' => $genre,
            'AlbumGenre2' => ZebralutionGenres::code($release->subgenre),
            'DistributionTerritory' => implode(' ', ['WW', ...array_map(fn (string $country): string => "-{$country}", $release->blocked_territories ?? [])]),
            'AlbumPreorderReleaseDate' => $this->date($release->preorder_on),
            'DontDeliverToShop' => implode(',', $release->blocked_services ?? []),
            'AllowSubscriptionService' => 'y',
            'AlbumLanguage' => mb_strtolower((string) $release->language),
            'AlbumCopyrightInfo' => $this->credit($release->c_line),
            'ContainsAI_AlbumArtwork' => $release->ai_artwork,
            'AlbumProductionInfo' => $this->credit($release->p_line),
            'BookletIncluded' => 'n',
        ];

        return array_values($tracks->map(function (Track $track) use ($release, $album, $numbers, $releaseDate, $digitalDate, $priceGroup, $genre): array {
            $credits = $track->credits ?? [];
            $artists = ArtistCredits::names($track, ArtistRole::Primary) ?: ArtistCredits::names($release, ArtistRole::Primary);

            return array_map(fn (?string $cell): string => trim((string) $cell) === '' ? '-' : trim((string) $cell), [
                ...array_fill_keys(array_keys(self::COLUMNS), null),
                ...$album,
                'ISRC' => Identifiers::isrc($track->isrc),
                'TrackArtist' => implode(', ', $artists),
                'TrackArtistAdd' => $this->credits($track, $artists),
                'TrackTitle' => $track->name,
                'TrackVersion' => $track->version,
                'TrackDuration' => $track->duration === null ? null : gmdate('H:i:s', $track->duration),
                'TrackPrelistenStartSecond' => $track->preview_start === null ? null : (string) $track->preview_start,
                'TrackNumber' => (string) $numbers[$track->id],
                'SetNumber' => (string) ($track->disc_number ?? 1),
                'TrackLanguage' => $this->language($release, $track),
                'TrackLive' => preg_match('/\blive\b/i', (string) $track->version) === 1 ? 'y' : 'n',
                'TrackType' => 'audio',
                'TrackSaleAllowed' => 'y',
                'TrackPriceGroup' => $priceGroup,
                'TrackReleaseDate' => $releaseDate,
                'TrackDigitalReleaseDate' => $digitalDate,
                'TrackPreorderType' => $release->preorder_on === null ? null : 'standard',
                'TrackGenre' => ZebralutionGenres::code($track->genre) ?? $genre,
                'CopyrightInfo' => $this->credit($release->c_line),
                'ProductionInfo' => $this->credit($track->p_line ?? $release->p_line),
                'TrackExplicitLyrics' => match ($track->explicit ?? $release->explicit) {
                    'explicit' => 'y',
                    'clean' => 'cleaned',
                    default => 'n',
                },
                'OriginCountry' => strtoupper((string) $release->label->deliverySetting('zebralution.origin_country')),
                'TrackLyricist' => implode(', ', $credits['lyricists'] ?? []),
                'TrackComposer' => implode(', ', $credits['composers'] ?? []) ?: 'n/a',
                'ISWC' => $track->iswc,
                'TrackProducer' => implode(', ', array_unique([...ArtistCredits::names($track, ArtistRole::Producer), ...($credits['producers'] ?? [])])),
                'TrackPublisher' => implode(', ', $credits['publishers'] ?? []),
                'IsPublicDomain' => 'n',
                'IsFunctionalContent' => 'n',
                'ContainsAI' => $track->ai_recording,
                'ContainsAI_Composition' => $track->ai_composition,
                'ContainsAI_Lyrics' => $track->ai_lyrics,
                // Only music videos have visuals to declare.
                'ContainsAI_VideoVisual' => 'none',
                'MusicalWorkRights' => (string) $release->label->deliverySetting('zebralution.work_rights', self::WORK_RIGHTS[0]),
                'MusicPercentage' => '100',
            ]);
        })->all());
    }

    /**
     * The rows as an Excel workbook laid out like Zebralution's own:
     * a sheet "Metadata" with the column names in line 5 and the tracks
     * from line 6. Every cell is text, so no code loses its leading zero.
     *
     * @param  list<array<string, string>>  $rows
     */
    public function workbook(array $rows): string
    {
        $lines = [
            1 => [self::VERSION],
            2 => [self::BUILD],
            4 => array_map(fn (bool $mandatory): string => $mandatory ? 'Mandatory' : 'Optional', array_values(self::COLUMNS)),
            5 => array_keys(self::COLUMNS),
        ];

        foreach ($rows as $row) {
            $lines[] = array_values($row);
        }

        $sheet = '';

        foreach ($lines as $number => $cells) {
            $sheet .= "<row r=\"{$number}\">";

            foreach ($cells as $index => $cell) {
                $sheet .= sprintf('<c r="%s%d" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>', $this->column($index), $number, htmlspecialchars($cell, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
            }

            $sheet .= '</row>';
        }

        $main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $office = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $package = 'http://schemas.openxmlformats.org/package/2006';
        $declaration = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $path = tempnam(sys_get_temp_dir(), 'zebralution-sheet');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The metadata sheet could not be created.');
        }

        $zip->addFromString('[Content_Types].xml', "{$declaration}<Types xmlns=\"{$package}/content-types\">"
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', "{$declaration}<Relationships xmlns=\"{$package}/relationships\"><Relationship Id=\"rId1\" Type=\"{$office}/officeDocument\" Target=\"xl/workbook.xml\"/></Relationships>");
        $zip->addFromString('xl/workbook.xml', "{$declaration}<workbook xmlns=\"{$main}\" xmlns:r=\"{$office}\"><sheets><sheet name=\"Metadata\" sheetId=\"1\" r:id=\"rId1\"/></sheets></workbook>");
        $zip->addFromString('xl/_rels/workbook.xml.rels', "{$declaration}<Relationships xmlns=\"{$package}/relationships\"><Relationship Id=\"rId1\" Type=\"{$office}/worksheet\" Target=\"worksheets/sheet1.xml\"/></Relationships>");
        $zip->addFromString('xl/worksheets/sheet1.xml', "{$declaration}<worksheet xmlns=\"{$main}\"><sheetData>{$sheet}</sheetData></worksheet>");
        $zip->close();

        $workbook = (string) file_get_contents($path);
        @unlink($path);

        return $workbook;
    }

    /**
     * The code of the release as Zebralution files it: thirteen digits,
     * a UPC with zeros in front.
     */
    public static function ean(Release $release): string
    {
        return str_pad((string) Identifiers::upc($release->upc), 13, '0', STR_PAD_LEFT);
    }

    /**
     * "131: Main Artist; 501: Guest": every artist credit with the code of its role.
     *
     * @param  list<string>|null  $primary  the main artists, when they are not the record's own
     */
    private function credits(Release|Track $record, ?array $primary = null): string
    {
        $credits = array_map(fn (string $name): string => "131: {$name}", $primary ?? ArtistCredits::names($record, ArtistRole::Primary));

        foreach ($record->artists as $artist) {
            /** @var Artist $artist */
            $code = self::ROLE_CODES[$artist->pivot->role] ?? null;

            if ($code !== null && $code !== 131) {
                $credits[] = "{$code}: {$artist->name}";
            }
        }

        // Remixers may also be kept as plain names with the track.
        foreach ($record instanceof Track ? ($record->credits['remixers'] ?? []) : [] as $name) {
            $credits[] = "506: {$name}";
        }

        return implode('; ', array_unique($credits));
    }

    /**
     * Single track, EP (nine tracks at most) or album.
     */
    private function mediaType(Release $release, int $tracks): string
    {
        return match (true) {
            in_array($release->release_type, [ReleaseType::Album, ReleaseType::Compilation], true), $tracks > 9 => '8',
            $tracks <= 1 => '6',
            default => '7',
        };
    }

    /**
     * The language sung or spoken on the track; without one of its own, the release's.
     */
    private function language(Release $release, Track $track): string
    {
        return mb_strtolower(trim((string) ($track->language ?? $release->language)));
    }

    /**
     * A © or ℗ line without its sign, which Zebralution adds itself.
     */
    private function credit(?string $line): string
    {
        return trim((string) preg_replace('/^\s*(?:[©℗]|\([cp]\))\s*/iu', '', (string) $line));
    }

    private function startsWithYear(?string $line): bool
    {
        return preg_match('/^(?:19|20)\d\d\s+\S/', $this->credit($line)) === 1;
    }

    private function date(?CarbonImmutable $date): ?string
    {
        return $date?->format('Ymd');
    }

    /**
     * 0 is "A", 26 is "AA".
     */
    private function column(int $index): string
    {
        $name = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $name = chr(65 + ($index - 1) % 26).$name;
        }

        return $name;
    }
}
