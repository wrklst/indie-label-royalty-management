<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrackRequest;
use App\Models\Artist;
use App\Models\Contract;
use App\Models\Release;
use App\Models\Track;
use App\Services\Imports\Identifiers;
use App\Services\Releases\ArtistCredits;
use App\Support\Options;
use App\Support\Sorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrackController extends Controller
{
    public function index(Request $request): Response
    {
        $sorting = Sorting::for($request, [
            'name' => 'name',
            'artist' => Sorting::related(Artist::class, 'artist_id'),
            'release' => Sorting::related(Release::class, 'release_id'),
            'isrc' => Sorting::nullsLast('isrc'),
            'contract' => Sorting::related(Contract::class, 'contract_id'),
        ], fn (Builder $query) => $query->orderBy('name'));

        $tracks = Track::query()
            ->with(['artist:id,name', 'contract:id,name', 'release:id,name,catalog_number,contract_id', 'release.contract:id,name'])
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('isrc', 'like', '%'.str_replace('-', '', $search).'%')))
            ->when($request->integer('release'), fn ($query, int $release) => $query->where('release_id', $release))
            ->tap($sorting)
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
            'filters' => ['search' => $request->string('search')->value(), 'release' => $request->integer('release') ?: null, ...$sorting->toArray()],
            'artistOptions' => Options::artists(),
            'contractOptions' => Options::contracts(),
            'releaseOptions' => Options::releases(),
        ]);
    }

    public function update(TrackRequest $request, Track $track): RedirectResponse
    {
        $attributes = $request->safe()->except('artists');

        if ($request->has('artists')) {
            ArtistCredits::sync($track, $request->validated('artists', []));
        }

        if (array_key_exists('isrc', $attributes)) {
            $attributes['isrc'] = Identifiers::isrc($attributes['isrc']);
        }

        $track->update($attributes);

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
