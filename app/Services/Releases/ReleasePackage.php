<?php

namespace App\Services\Releases;

use App\Enums\ArtistRole;
use App\Enums\ReleaseFileKind;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\Track;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Bundles a release for delivery: cover, audio named after the track
 * list, the metadata sheet and a text sheet to copy from when a store
 * such as Bandcamp only offers a web form.
 */
class ReleasePackage
{
    public function __construct(private readonly ReleaseMetadataExport $metadata) {}

    public function folderName(Release $release): string
    {
        return $this->fileName(implode(' - ', array_filter([$release->catalog_number, ArtistCredits::display($release), $release->name])));
    }

    /**
     * Build the archive and return the path of the temporary zip file.
     */
    public function build(Release $release): string
    {
        $release->loadMissing(['artist', 'artists', 'label', 'files', 'tracks.artist', 'tracks.artists']);

        $path = tempnam(sys_get_temp_dir(), 'release-package');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The package could not be created.');
        }

        $folder = $this->folderName($release);
        $used = [];
        $add = function (ReleaseFile $file, string $name) use ($zip, $folder, &$used): void {
            $source = Storage::disk($file->disk)->path($file->path);

            if (! is_file($source)) {
                return;
            }

            // Two files may ask for the same name; the later one gets a number.
            $candidate = $name;
            $counter = 2;

            while (isset($used[mb_strtolower($candidate)])) {
                $candidate = preg_replace('/(\.[^.\/]+)?$/', " ({$counter})$1", $name, 1) ?? $name;
                $counter++;
            }

            $used[mb_strtolower($candidate)] = true;
            $zip->addFile($source, "{$folder}/{$candidate}");
            // Audio and images are already compressed or must stay bit-identical.
            $zip->setCompressionName("{$folder}/{$candidate}", ZipArchive::CM_STORE);
        };

        foreach ($release->files->sortBy('id') as $file) {
            $extension = pathinfo($file->original_name, PATHINFO_EXTENSION);
            $suffix = $extension === '' ? '' : '.'.strtolower($extension);

            match ($file->kind) {
                ReleaseFileKind::Cover => $add($file, "cover{$suffix}"),
                ReleaseFileKind::Audio => $add($file, 'audio/'.$this->audioName($release, $file).$suffix),
                ReleaseFileKind::Artwork => $add($file, 'artwork/'.$this->fileName(pathinfo($file->original_name, PATHINFO_FILENAME)).$suffix),
                default => $add($file, 'documents/'.$this->fileName(pathinfo($file->original_name, PATHINFO_FILENAME)).$suffix),
            };
        }

        $sheet = fopen('php://temp', 'r+');

        if ($sheet === false) {
            throw new RuntimeException('The metadata sheet could not be created.');
        }

        $this->metadata->write($sheet, $this->metadata->rows($release));
        rewind($sheet);
        $zip->addFromString("{$folder}/metadata.csv", (string) stream_get_contents($sheet));
        fclose($sheet);

        $zip->addFromString("{$folder}/release.txt", $this->labelCopy($release));
        $zip->close();

        return $path;
    }

    /**
     * Everything about the release as plain text, in the order store
     * upload forms ask for it.
     */
    public function labelCopy(Release $release): string
    {
        $release->loadMissing(['artist', 'artists', 'label', 'tracks.artist', 'tracks.artists']);

        $lines = [
            'Title: '.$release->name.($release->version ? " ({$release->version})" : ''),
            'Artist: '.(ArtistCredits::display($release) ?? ''),
            'Label: '.$release->label->name,
            'Catalog number: '.($release->catalog_number ?? ''),
            'UPC / EAN: '.($release->upc ?? ''),
            'Format: '.($release->release_type?->label() ?? ''),
            'Release date: '.($release->released_on?->toDateString() ?? ''),
            'Genre: '.implode(' / ', array_filter([$release->genre, $release->subgenre])),
            '(P) '.($release->p_line ?? ''),
            '(C) '.($release->c_line ?? ''),
        ];

        if ($release->blocked_services !== null && $release->blocked_services !== []) {
            $lines[] = 'Do not deliver to: '.implode(', ', $release->blocked_services);
        }

        if ($release->blocked_territories !== null && $release->blocked_territories !== []) {
            $lines[] = 'Blocked territories: '.implode(', ', $release->blocked_territories);
        }

        $lines[] = '';
        $lines[] = 'TRACKS';

        foreach ($release->tracks->sortBy([['disc_number', 'asc'], ['track_number', 'asc'], ['id', 'asc']]) as $track) {
            $lines[] = trim(sprintf(
                '%s. %s - %s%s%s%s',
                str_pad((string) ($track->track_number ?? '?'), 2, '0', STR_PAD_LEFT),
                ArtistCredits::display($track) ?? ArtistCredits::display($release) ?? '',
                $track->name,
                $track->version ? " ({$track->version})" : '',
                $track->duration ? sprintf(' [%d:%02d]', intdiv($track->duration, 60), $track->duration % 60) : '',
                $track->isrc ? "  ISRC {$track->isrc}" : '',
            ));
        }

        foreach (['PRESS RELEASE' => $release->press_release, 'KEY FACTS' => $release->key_facts] as $heading => $text) {
            if ($text !== null && trim($text) !== '') {
                array_push($lines, '', $heading, trim($text));
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * "01 - Example Artist - Example Track (Remix)" for a file attached
     * to a track; otherwise the name it was uploaded with.
     */
    private function audioName(Release $release, ReleaseFile $file): string
    {
        $track = $file->track_id === null ? null : $release->tracks->firstWhere('id', $file->track_id);

        if (! $track instanceof Track) {
            return $this->fileName(pathinfo($file->original_name, PATHINFO_FILENAME));
        }

        $artist = implode(' & ', ArtistCredits::names($track, ArtistRole::Primary)) ?: (ArtistCredits::display($release) ?? '');

        return $this->fileName(sprintf(
            '%s - %s%s%s',
            str_pad((string) ($track->track_number ?? 0), 2, '0', STR_PAD_LEFT),
            $artist !== '' ? "{$artist} - " : '',
            $track->name,
            $track->version ? " ({$track->version})" : '',
        ));
    }

    /**
     * Remove what file systems and zip tools choke on.
     */
    private function fileName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', preg_replace('/[\/\\\\:*?"<>|\x00-\x1F]/u', ' ', $name) ?? '') ?? '', ' .');

        return $name === '' ? 'untitled' : mb_substr($name, 0, 150);
    }
}
