<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArtistRequest;
use App\Models\Artist;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArtistController extends Controller
{
    public function index(Request $request): Response
    {
        $artists = Artist::query()
            ->with('aliasOf:id,name')
            ->withCount(['releases', 'tracks', 'aliases'])
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Artist $artist): array => [
                ...$artist->only(['id', 'name', 'alias_of_id', 'spotify_id', 'discogs_search_name', 'notes', 'releases_count', 'tracks_count', 'aliases_count']),
                'alias_of' => $artist->aliasOf?->name,
            ]);

        return Inertia::render('artists/index', [
            'artists' => $artists,
            'filters' => ['search' => $request->string('search')->value()],
            'artistOptions' => Options::artists(),
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
