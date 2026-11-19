<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\Track;
use App\Support\CurrentLabel;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The catalog manager: every release with its tracks and the contract
 * each one resolves to, for assigning contracts in one place.
 */
class CatalogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('catalog/manager', [
            'releases' => Release::query()
                ->with(['artist:id,name', 'tracks' => fn ($query) => $query->orderBy('track_number')->orderBy('id'), 'tracks.artist:id,name'])
                ->orderByRaw('catalog_number IS NULL')->orderBy('catalog_number')->orderBy('name')
                ->get()
                ->map(fn (Release $release): array => [
                    ...$release->only(['id', 'name', 'catalog_number', 'contract_id', 'artist_id']),
                    'artist' => $release->artist?->name,
                    'tracks' => $release->tracks->map(fn (Track $track): array => [
                        ...$track->only(['id', 'name', 'isrc', 'contract_id', 'artist_id']),
                        'artist' => $track->artist?->name,
                    ]),
                ]),
            'contractOptions' => Options::contracts(),
        ]);
    }

    /**
     * Set or clear the contract of one release or track.
     */
    public function assign(Request $request, CurrentLabel $currentLabel): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['release', 'track'])],
            'id' => ['required', 'integer'],
            'contract_id' => ['nullable', 'integer', Rule::exists('contracts', 'id')->where('label_id', $currentLabel->id())],
        ]);

        $model = $validated['type'] === 'release' ? Release::class : Track::class;
        $model::query()->whereKey($validated['id'])->firstOrFail()->update(['contract_id' => $validated['contract_id'] ?? null]);

        return back();
    }
}
