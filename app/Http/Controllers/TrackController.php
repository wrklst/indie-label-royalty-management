<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrackRequest;
use App\Models\Track;
use App\Services\Imports\Identifiers;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrackController extends Controller
{
    public function index(Request $request): Response
    {
        $tracks = Track::query()
            ->with(['artist:id,name', 'contract:id,name', 'release:id,name,catalog_number,contract_id', 'release.contract:id,name'])
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('isrc', 'like', '%'.str_replace('-', '', $search).'%')))
            ->when($request->integer('release'), fn ($query, int $release) => $query->where('release_id', $release))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Track $track): array => [
                ...$track->only(['id', 'name', 'artist_id', 'contract_id', 'isrc', 'iswc', 'track_number', 'duration', 'mechanicals_rate', 'ppd_rate', 'release_id']),
                'artist' => $track->artist?->name,
                'release' => $track->release?->only(['id', 'name', 'catalog_number']),
                'contract' => $track->contract?->only(['id', 'name']),
                'inherited_contract' => $track->contract ? null : $track->release?->contract?->only(['id', 'name']),
            ]);

        return Inertia::render('tracks/index', [
            'tracks' => $tracks,
            'filters' => ['search' => $request->string('search')->value(), 'release' => $request->integer('release') ?: null],
            'artistOptions' => Options::artists(),
            'contractOptions' => Options::contracts(),
            'releaseOptions' => Options::releases(),
        ]);
    }

    public function update(TrackRequest $request, Track $track): RedirectResponse
    {
        $track->update([...$request->validated(), 'isrc' => Identifiers::isrc($request->validated('isrc'))]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Track saved.')]);

        return back();
    }

    public function destroy(Track $track): RedirectResponse
    {
        $track->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Track deleted.')]);

        return back();
    }
}
