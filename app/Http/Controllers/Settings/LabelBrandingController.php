<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Label;
use App\Support\CurrentLabel;
use App\Support\WorkingCopy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The label's logo and its signet, the small mark shown where a logo
 * does not fit. Each is stored with the disk it is on, as "disk:path",
 * so it is still found after the file storage is changed.
 */
class LabelBrandingController extends Controller
{
    public const array KINDS = ['logo', 'signet'];

    /**
     * The address of an image of the label, or null when it has none.
     * It changes with the file, so browsers may keep what they loaded.
     */
    public static function url(Label $label, string $kind): ?string
    {
        $stored = $label->{"{$kind}_path"};

        return $stored === null ? null : route('label-branding.show', ['kind' => $kind, 'v' => substr(md5($stored), 0, 8)]);
    }

    /**
     * Store an uploaded image and remove the one it replaces. Answers
     * with plain text, as the upload field expects.
     */
    public function store(Request $request, string $kind, CurrentLabel $currentLabel): Response
    {
        $label = $currentLabel->get() ?? abort(404);
        // No SVG: it can carry scripts and is shown to payees.
        $request->validate(['file' => ['required', 'file', 'max:4096', 'mimetypes:image/png,image/jpeg,image/webp']], [
            'file.mimetypes' => __('The image has to be a PNG, JPEG or WebP file.'),
            'file.max' => __('The image can be 4 MB at most.'),
        ]);

        $disk = WorkingCopy::disk();
        $file = $request->file('file');
        $path = $file->storeAs("branding/{$label->id}", $kind.'-'.Str::uuid().'.'.$file->extension(), $disk);

        $this->forget($label, $kind);
        $label->update(["{$kind}_path" => "{$disk}:{$path}"]);

        return response($kind, 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Show the image to anyone who belongs to the label, payees included.
     */
    public function show(string $kind, CurrentLabel $currentLabel): Response
    {
        $stored = ($currentLabel->get() ?? abort(404))->{"{$kind}_path"} ?? abort(404);
        [$disk, $path] = explode(':', $stored, 2);

        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, null, ['Cache-Control' => 'private, max-age=31536000, immutable', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(string $kind, CurrentLabel $currentLabel): Response
    {
        $label = $currentLabel->get() ?? abort(404);

        $this->forget($label, $kind);
        $label->update(["{$kind}_path" => null]);

        return back();
    }

    private function forget(Label $label, string $kind): void
    {
        if ($label->{"{$kind}_path"} !== null) {
            [$disk, $path] = explode(':', $label->{"{$kind}_path"}, 2);
            Storage::disk($disk)->delete($path);
        }
    }
}
