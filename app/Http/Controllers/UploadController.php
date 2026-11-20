<?php

namespace App\Http\Controllers;

use App\Support\CurrentLabel;
use App\Support\DirectUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UploadController extends Controller
{
    /**
     * Tell the browser where to send a file itself. Without a bucket the
     * address is null and the file is posted to this server as before.
     */
    public function sign(Request $request, CurrentLabel $currentLabel): JsonResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $origin = $request->getSchemeAndHttpHost();

        // A bucket that refuses this address is not tried at all. One that does not say is: the browser falls back by itself.
        if (! Cache::remember(DirectUploads::cacheKey($origin), 600, fn (): bool => DirectUploads::allowed($origin) !== false)) {
            return response()->json(['url' => null]);
        }

        return response()->json(DirectUploads::sign($currentLabel->id() ?? abort(404), $validated['name']) ?? ['url' => null]);
    }
}
