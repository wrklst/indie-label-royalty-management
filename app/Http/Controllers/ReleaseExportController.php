<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Services\Releases\ReleaseMetadataExport;
use App\Services\Releases\ReleasePackage;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReleaseExportController extends Controller
{
    /**
     * The metadata of every release, or of one, as a CSV sheet with one
     * line per track.
     */
    public function metadata(Request $request, ReleaseMetadataExport $export): StreamedResponse
    {
        $releases = Release::query()
            ->when($request->integer('release'), fn ($query, int $release) => $query->whereKey($release))
            ->with(['artist', 'artists', 'label', 'tracks.artist', 'tracks.artists'])
            ->orderByRaw('catalog_number IS NULL')->orderBy('catalog_number')->orderBy('name')
            ->get();

        abort_if($request->integer('release') !== 0 && $releases->isEmpty(), 404);

        $name = $releases->count() === 1 && $request->integer('release') !== 0
            ? str($releases[0]->catalog_number ?: $releases[0]->name)->slug().'-metadata.csv'
            : 'release-metadata.csv';

        return response()->streamDownload(function () use ($releases, $export): void {
            $stream = fopen('php://output', 'w');

            if ($stream === false) {
                throw new RuntimeException('The download stream could not be opened.');
            }

            $export->write($stream, $releases->flatMap(fn (Release $release): array => $export->rows($release)));
            fclose($stream);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /**
     * Everything needed to deliver one release, as a zip archive.
     */
    public function package(Release $release, ReleasePackage $package): BinaryFileResponse
    {
        return response()
            ->download($package->build($release), $package->folderName($release).'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
