<?php

namespace App\Http\Controllers;

use App\Enums\ReleaseFileKind;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Services\Releases\ReleaseFileStore;
use App\Support\CurrentLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Uploads and serves the files of a release. Files sit on the private
 * disk; this controller is the only way to reach them, so every request
 * is checked against the signed-in label.
 */
class ReleaseFileController extends Controller
{
    /** Never stored: they could be mistaken for something to run. */
    private const array REFUSED_EXTENSIONS = ['php', 'phtml', 'phar', 'html', 'htm', 'js', 'mjs', 'exe', 'sh', 'bat', 'cmd', 'svg'];

    public function store(Request $request, Release $release, ReleaseFileStore $files, CurrentLabel $currentLabel): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.config('royalties.uploads.max_kilobytes')],
            'kind' => ['nullable', Rule::enum(ReleaseFileKind::class)],
            'track_id' => ['nullable', 'integer', Rule::exists('tracks', 'id')->where('release_id', $release->id)],
        ], [
            'file.max' => 'The file is larger than the '.round(config('royalties.uploads.max_kilobytes') / 1024).' MB this server accepts in one upload.',
            'file.uploaded' => 'The upload did not arrive completely. The file may be larger than the server accepts.',
        ]);

        $upload = $request->file('file');

        if (in_array(strtolower($upload->getClientOriginalExtension()), self::REFUSED_EXTENSIONS, true)) {
            return back()->withErrors(['file' => 'Files of this type cannot be stored here.']);
        }

        $file = $files->store(
            $release,
            $upload,
            ReleaseFileKind::tryFrom((string) ($validated['kind'] ?? '')),
            isset($validated['track_id']) ? (int) $validated['track_id'] : null,
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name uploaded.', ['name' => $file->original_name])]);

        return back();
    }

    /**
     * Send a file to the browser. Audio and images are streamed in place
     * (with range requests, so the player can seek); anything else, or
     * any file when ?download=1 is given, is sent as a download.
     */
    public function show(Request $request, ReleaseFile $releaseFile): BinaryFileResponse
    {
        $disk = Storage::disk($releaseFile->disk);

        abort_unless($disk->exists($releaseFile->path), 404);

        $inline = ! $request->boolean('download') && $releaseFile->canBeShownInline();

        $response = response()->file($disk->path($releaseFile->path), [
            'Content-Type' => $inline ? (string) $releaseFile->mime_type : 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                $releaseFile->original_name,
                preg_replace('/[^\x20-\x7E]|[%\/\\\\]/', '_', $releaseFile->original_name) ?? 'file',
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);

        // Private files must not end up in shared caches.
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    /**
     * Change what a file is, or which track an audio file belongs to.
     */
    public function update(Request $request, ReleaseFile $releaseFile): RedirectResponse
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::enum(ReleaseFileKind::class)],
            'track_id' => ['nullable', 'integer', Rule::exists('tracks', 'id')->where('release_id', $releaseFile->release_id)],
        ]);

        $kind = ReleaseFileKind::from($validated['kind']);

        if ($kind === ReleaseFileKind::Cover) {
            if (! in_array($releaseFile->mime_type, ReleaseFileStore::COVER_TYPES, true)) {
                return back()->withErrors(['kind' => 'A cover must be a JPEG, PNG or WebP image.']);
            }

            ReleaseFile::query()->where('release_id', $releaseFile->release_id)->where('kind', ReleaseFileKind::Cover)
                ->whereKeyNot($releaseFile->id)->update(['kind' => ReleaseFileKind::Artwork]);
        }

        $releaseFile->update([
            'kind' => $kind,
            'track_id' => $kind === ReleaseFileKind::Audio ? ($validated['track_id'] ?? null) : null,
        ]);

        return back();
    }

    public function destroy(ReleaseFile $releaseFile): RedirectResponse
    {
        $releaseFile->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('File deleted.')]);

        return back();
    }
}
