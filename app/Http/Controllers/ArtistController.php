<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArtistRequest;
use App\Models\Artist;
use App\Models\Release;
use App\Models\Track;
use App\Support\Options;
use App\Support\Sorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArtistController extends Controller
{
    public function index(Request $request): Response
    {
        $sorting = Sorting::for($request, [
            'name' => 'name',
            'releases_count' => 'releases_count',
            'tracks_count' => 'tracks_count',
            'notes' => Sorting::nullsLast('notes'),
        ], fn (Builder $query) => $query->orderBy('name'));

        $artists = Artist::query()
            ->with('aliasOf:id,name')
            ->withCount(['releases', 'tracks', 'aliases'])
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->tap($sorting)
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Artist $artist): array => [
                ...$artist->only(['id', 'name', 'alias_of_id', 'spotify_id', 'discogs_search_name', 'notes', 'releases_count', 'tracks_count', 'aliases_count']),
                'alias_of' => $artist->aliasOf?->name,
            ]);

        return Inertia::render('artists/index', [
            'artists' => $artists,
            'filters' => ['search' => $request->string('search')->value(), ...$sorting->toArray()],
            'artistOptions' => Options::artists(),
        ]);
    }

    /**
     * The releases and tracks of one artist, and where the tracks were played.
     */
    public function show(Artist $artist): Response
    {
        $tracks = Track::query()->where(fn ($query) => $query->where('artist_id', $artist->id)->orWhereHas('artists', fn ($query) => $query->whereKey($artist->id)))
            ->with('release:id,name')->withCount('airplays')->orderBy('name')->get();

        return Inertia::render('artists/show', [
            'artist' => $artist->only(['id', 'name']),
            'releases' => Release::query()->where(fn ($query) => $query->where('artist_id', $artist->id)->orWhereHas('artists', fn ($query) => $query->whereKey($artist->id)))
                ->orderByDesc('released_on')->orderBy('name')->get(['id', 'name', 'catalog_number', 'released_on'])
                ->map(fn (Release $release): array => [
                    ...$release->only(['id', 'name', 'catalog_number']),
                    'released_on' => $release->released_on?->toDateString(),
                ]),
            'tracks' => $tracks->map(fn (Track $track): array => [
                ...$track->only(['id', 'name', 'isrc']),
                'release' => $track->release?->only(['id', 'name']),
                'airplays_count' => $track->airplays_count,
            ]),
        ]);
    }

    public function store(ArtistRequest $request): RedirectResponse
    {
        Artist::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Artist created.')]);

        return back();
    }

    public function update(ArtistRequest $request, Artist $artist): RedirectResponse
    {
        $artist->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Artist updated.')]);

        return back();
    }

    public function destroy(Artist $artist): RedirectResponse
    {
        $artist->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Artist deleted.')]);

        return back();
    }
}
