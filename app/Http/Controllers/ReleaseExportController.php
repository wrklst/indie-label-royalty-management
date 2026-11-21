<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Services\Releases\BandcampPackage;
use App\Services\Releases\ReleaseMetadataExport;
use App\Services\Releases\ReleasePackage;
use App\Services\Releases\SubvertPackage;
use App\Services\Releases\ZebralutionPackage;
use App\Support\Distributors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReleaseExportController extends Controller
{
    private const int ARCHIVE_SECONDS = 900;

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
        $this->allowLongRequest();

        return $this->archive($package->build($release), $package->folderName($release).'.zip');
    }

    /**
     * The cover and one audio file per track as the single ZIP file
     * Subvert's uploader takes. When something is missing the release
     * page says what, instead of a download that Subvert would reject.
     */
    public function subvertPackage(Release $release, SubvertPackage $package): BinaryFileResponse|RedirectResponse
    {
        return $this->storePackage($release, $package, 'subvert_package');
    }

    /**
     * The cover, one lossless audio file per track and a sheet to copy
     * from, to unpack and add on Bandcamp's album page.
     */
    public function bandcampPackage(Release $release, BandcampPackage $package): BinaryFileResponse|RedirectResponse
    {
        return $this->storePackage($release, $package, 'bandcamp_package');
    }

    /**
     * Zebralution's metadata sheet filled in, the cover, and the WAV
     * masters in the folder and under the names its FTP server asks
     * for. When Zebralution would send the release back, the release
     * page says why instead.
     */
    public function zebralutionPackage(Release $release, ZebralutionPackage $package): BinaryFileResponse|RedirectResponse
    {
        abort_unless($release->label->usesDistributor(Distributors::ZEBRALUTION), 404);

        return $this->storePackage($release, $package, 'zebralution_package');
    }

    private function storePackage(Release $release, SubvertPackage $package, string $errors): BinaryFileResponse|RedirectResponse
    {
        $release->load(['artist', 'artists', 'label', 'files', 'tracks.artist', 'tracks.artists']);
        $problems = $package->problems($release);

        if ($problems !== []) {
            return to_route('releases.show', $release)->withErrors([$errors => implode("\n", $problems)]);
        }

        $this->allowLongRequest();

        return $this->archive($package->build($release), $package->fileName($release));
    }

    /**
     * Send a temporary archive and remove it afterwards, also when the
     * download is cancelled half way.
     */
    private function archive(string $path, string $name): BinaryFileResponse
    {
        return response()
            ->download($path, $name, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    /**
     * Copying gigabytes of masters into an archive and sending it takes
     * longer than a page may; these downloads get a quarter of an hour.
     * The request also runs on after the browser went away, so that the
     * temporary archive is still removed.
     */
    private function allowLongRequest(): void
    {
        $limit = (int) ini_get('max_execution_time');

        if ($limit !== 0 && $limit < self::ARCHIVE_SECONDS) {
            set_time_limit(self::ARCHIVE_SECONDS);
        }

        ignore_user_abort(true);
    }
}
