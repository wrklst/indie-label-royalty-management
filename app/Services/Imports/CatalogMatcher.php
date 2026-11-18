<?php

namespace App\Services\Imports;

use App\Models\Artist;
use App\Models\Item;
use App\Models\Label;
use App\Models\Release;
use App\Models\Track;

/**
 * Links statement lines to the catalog and builds missing entries.
 *
 * A release is found by UPC/EAN, then catalog number. A track is found by
 * ISRC within that release. Whatever is missing gets created from the
 * metadata in the file, so a first import builds the whole catalog. Lines
 * that carry too little to identify a release end up on an unassigned item
 * for manual review instead of being guessed.
 */
class CatalogMatcher
{
    /** @var array<string, int> */
    private array $releasesByUpc = [];

    /** @var array<string, int> */
    private array $releasesByCatalog = [];

    /** @var array<string, int> */
    private array $releasesByName = [];

    /** @var array<string, int> "releaseId|isrc" => track id */
    private array $tracksByReleaseIsrc = [];

    /** @var array<string, int> "releaseId|name" => track id */
    private array $tracksByReleaseName = [];

    /** @var array<string, array{0: int, 1: int}> isrc => [track id, release id] */
    private array $tracksByIsrc = [];

    /** @var array<string, int> */
    private array $artists = [];

    /** @var array<string, int> */
    private array $items = [];

    public int $createdReleases = 0;

    public int $createdTracks = 0;

    public function __construct(private readonly Label $label, private readonly ?int $dataImportId = null)
    {
        Release::query()->where('label_id', $label->id)->orderBy('id')
            ->get(['id', 'name', 'upc', 'catalog_number'])
            ->each(fn (Release $release) => $this->rememberRelease($release));

        Track::query()->where('label_id', $label->id)->orderBy('id')
            ->get(['id', 'release_id', 'name', 'isrc'])
            ->each(fn (Track $track) => $this->rememberTrack($track));

        Artist::query()->where('label_id', $label->id)->orderBy('id')->get(['id', 'name'])
            ->each(function (Artist $artist): void {
                $this->artists[Identifiers::nameKey($artist->name)] ??= $artist->id;
            });

        Item::query()->where('label_id', $label->id)->orderBy('id')
            ->get(['id', 'release_id', 'track_id', 'format_group', 'isrc', 'upc', 'name', 'is_additionals'])
            ->each(function (Item $item): void {
                if (! $item->is_additionals) {
                    $this->items[$this->itemKey($item->release_id, $item->track_id, $item->format_group, $item->isrc, $item->upc, $item->name)] ??= $item->id;
                }
            });
    }

    /**
     * @return array{release_id: int|null, track_id: int|null, item_id: int|null}
     */
    public function match(ImportRow $row, ?string $formatGroup): array
    {
        $hasCatalogData = $row->upc || $row->isrc || $row->catalogNumber || $row->releaseName || $row->trackName;

        if (! $hasCatalogData) {
            return ['release_id' => null, 'track_id' => null, 'item_id' => null];
        }

        $releaseId = $this->findRelease($row);
        $trackId = null;

        $namesRelease = $row->upc || $row->catalogNumber || $row->releaseName;

        if ($releaseId === null && ! $namesRelease && $row->isrc && isset($this->tracksByIsrc[$row->isrc])) {
            [$trackId, $releaseId] = $this->tracksByIsrc[$row->isrc];
        }

        if ($releaseId === null && $namesRelease) {
            $releaseId = $this->createRelease($row);
        }

        if ($releaseId !== null && $trackId === null && ($row->isrc || $row->trackName)) {
            $trackId = $this->findTrack($releaseId, $row) ?? $this->createTrack($releaseId, $row);
        }

        return [
            'release_id' => $releaseId,
            'track_id' => $trackId,
            'item_id' => $this->item($releaseId, $trackId, $formatGroup, $row),
        ];
    }

    private function findRelease(ImportRow $row): ?int
    {
        if ($row->upc && isset($this->releasesByUpc[Identifiers::upcKey($row->upc)])) {
            return $this->releasesByUpc[Identifiers::upcKey($row->upc)];
        }

        $catalogKey = Identifiers::catalogKey($row->catalogNumber);

        if (! $row->upc && $catalogKey && isset($this->releasesByCatalog[$catalogKey])) {
            return $this->releasesByCatalog[$catalogKey];
        }

        $nameKey = Identifiers::nameKey($row->releaseName);

        if (! $row->upc && ! $catalogKey && $nameKey && isset($this->releasesByName[$nameKey])) {
            return $this->releasesByName[$nameKey];
        }

        return null;
    }

    private function findTrack(int $releaseId, ImportRow $row): ?int
    {
        if ($row->isrc) {
            return $this->tracksByReleaseIsrc["{$releaseId}|{$row->isrc}"] ?? null;
        }

        return $this->tracksByReleaseName[$releaseId.'|'.Identifiers::nameKey($row->trackName)] ?? null;
    }

    private function createRelease(ImportRow $row): int
    {
        $release = Release::query()->create([
            'label_id' => $this->label->id,
            'artist_id' => $this->artist($row->releaseArtist ?: $row->artist),
            'name' => $row->releaseName ?: $row->catalogNumber ?: $row->upc,
            'catalog_number' => $row->catalogNumber ?: null,
            'upc' => $row->upc,
        ]);

        $this->rememberRelease($release);
        $this->createdReleases++;

        return $release->id;
    }

    private function createTrack(int $releaseId, ImportRow $row): int
    {
        $track = Track::query()->create([
            'label_id' => $this->label->id,
            'release_id' => $releaseId,
            'artist_id' => $this->artist($row->artist),
            'name' => $row->trackName ?: $row->isrc,
            'isrc' => $row->isrc,
        ]);

        $this->rememberTrack($track);
        $this->createdTracks++;

        return $track->id;
    }

    private function artist(?string $name): ?int
    {
        $key = Identifiers::nameKey($name);

        if ($key === null) {
            return null;
        }

        return $this->artists[$key] ??= Artist::query()->create([
            'label_id' => $this->label->id,
            'name' => trim((string) $name),
        ])->id;
    }

    private function item(?int $releaseId, ?int $trackId, ?string $formatGroup, ImportRow $row): int
    {
        $name = $row->trackName ?: $row->releaseName ?: $row->isrc ?: $row->upc ?: $row->catalogNumber ?: 'Unknown';
        $key = $this->itemKey($releaseId, $trackId, $formatGroup, $row->isrc, $row->upc, $name);

        return $this->items[$key] ??= Item::query()->create([
            'label_id' => $this->label->id,
            'release_id' => $releaseId,
            'track_id' => $trackId,
            'data_import_id' => $this->dataImportId,
            'format_group' => $formatGroup,
            'name' => $name,
            'artist_name' => $row->artist ?: $row->releaseArtist,
            'track_name' => $row->trackName,
            'catalog_number' => $row->catalogNumber ?: null,
            'isrc' => $row->isrc,
            'upc' => $row->upc,
        ])->id;
    }

    private function itemKey(?int $releaseId, ?int $trackId, ?string $formatGroup, ?string $isrc, ?string $upc, ?string $name): string
    {
        if ($releaseId !== null) {
            return implode('|', ['r', $releaseId, $trackId, $formatGroup]);
        }

        return implode('|', ['u', $isrc, Identifiers::upcKey($upc), $isrc || $upc ? '' : Identifiers::nameKey($name), $formatGroup]);
    }

    private function rememberRelease(Release $release): void
    {
        if ($key = Identifiers::upcKey($release->upc)) {
            $this->releasesByUpc[$key] ??= $release->id;
        }

        if ($key = Identifiers::catalogKey($release->catalog_number)) {
            $this->releasesByCatalog[$key] ??= $release->id;
        }

        if ($key = Identifiers::nameKey($release->name)) {
            $this->releasesByName[$key] ??= $release->id;
        }
    }

    private function rememberTrack(Track $track): void
    {
        if ($isrc = Identifiers::isrc($track->isrc)) {
            $this->tracksByReleaseIsrc["{$track->release_id}|{$isrc}"] ??= $track->id;
            $this->tracksByIsrc[$isrc] ??= [$track->id, $track->release_id];
        }

        if ($key = Identifiers::nameKey($track->name)) {
            $this->tracksByReleaseName["{$track->release_id}|{$key}"] ??= $track->id;
        }
    }
}
