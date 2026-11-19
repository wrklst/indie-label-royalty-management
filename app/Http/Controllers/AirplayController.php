<?php

namespace App\Http\Controllers;

use App\Enums\AirplayOrigin;
use App\Http\Requests\AirplayRequest;
use App\Models\Airplay;
use App\Models\AirplayFinding;
use App\Models\Release;
use App\Models\Track;
use App\Services\Airplay\AirplayResearch;
use App\Services\Airplay\WebSearch;
use App\Services\Releases\ArtistCredits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class AirplayController extends Controller
{
    public function index(WebSearch $search, AirplayResearch $research): Response
    {
        return Inertia::render('airplays/index', [
            'findings' => AirplayFinding::query()->where('status', AirplayFinding::NEW)->with('track:id,name')
                ->orderByDesc('is_radio')->latest()->limit(200)->get()
                ->map(fn (AirplayFinding $finding): array => [
                    ...$finding->only(['id', 'is_radio', 'station', 'url', 'title', 'description', 'domain', 'query', 'airplay_id']),
                    'track' => $finding->track?->name,
                ]),
            'releases' => Release::query()->orderBy('name')->get(['id', 'name']),
            'staleCount' => $research->stale(Track::query())->count(),
            'staleDays' => AirplayResearch::STALE_DAYS,
            // ponytail: the whole list in one page; paginate when a label has thousands of plays.
            'airplays' => Airplay::query()->orderBy('station')->orderByDesc('period_end')->orderBy('track_name')->get()
                ->map(fn (Airplay $airplay): array => [
                    ...$airplay->only(['id', 'origin', 'track_id', 'track_name', 'artist_name', 'isrc', 'station', 'media_type', 'region', 'society', 'amount', 'notes', 'data_import_id']),
                    'period_start' => $airplay->period_start?->toDateString(),
                    'period_end' => $airplay->period_end?->toDateString(),
                    'links' => $airplay->links ?? [],
                    'search_query' => $airplay->searchQuery(),
                ]),
            'tracks' => Track::query()->with(['artist:id,name', 'artists:id,name'])->orderBy('name')->get()
                ->map(fn (Track $track): array => [
                    'id' => $track->id,
                    'name' => collect([ArtistCredits::display($track), $track->name])->filter()->implode(' – '),
                ]),
            'searchAvailable' => $search->isConfigured(),
        ]);
    }

    public function store(AirplayRequest $request): RedirectResponse
    {
        Airplay::query()->create([...$this->attributes($request), 'origin' => AirplayOrigin::Manual]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Airplay added.')]);

        return back();
    }

    public function update(AirplayRequest $request, Airplay $airplay): RedirectResponse
    {
        $airplay->update($this->attributes($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Airplay updated.')]);

        return back();
    }

    public function destroy(Airplay $airplay): RedirectResponse
    {
        $airplay->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Airplay deleted.')]);

        return back();
    }

    public function storeLink(Request $request, Airplay $airplay): RedirectResponse
    {
        $link = $request->validate([
            'url' => ['required', 'url:http,https', 'max:2000'],
            'title' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', Rule::in([AirplayOrigin::Search->value, AirplayOrigin::Manual->value])],
        ]);

        $airplay->addLink($link, AirplayOrigin::from($link['source'] ?? AirplayOrigin::Manual->value));
        $airplay->save();

        return back();
    }

    public function destroyLink(Request $request, Airplay $airplay): RedirectResponse
    {
        $url = $request->validate(['url' => ['required', 'string']])['url'];
        $links = collect($airplay->links)->reject(fn (array $link): bool => $link['url'] === $url)->values()->all();

        $airplay->update(['links' => $links === [] ? null : $links]);

        return back();
    }

    /**
     * Look the play up on the web. Each call is billed by the search service.
     */
    public function search(Request $request, Airplay $airplay, WebSearch $search): JsonResponse
    {
        $query = $request->validate(['query' => ['nullable', 'string', 'max:300']])['query'] ?? null;

        try {
            return response()->json(['results' => $search->search($query ?: $airplay->searchQuery())]);
        } catch (RuntimeException $exception) {
            report($exception);

            throw ValidationException::withMessages(['query' => $exception->getMessage()]);
        }
    }

    /**
     * A play of a track in the catalog takes its names from the track.
     *
     * @return array<string, mixed>
     */
    private function attributes(AirplayRequest $request): array
    {
        $attributes = $request->validated();
        $track = isset($attributes['track_id']) ? Track::query()->with(['artist:id,name', 'artists:id,name'])->find($attributes['track_id']) : null;

        if ($track === null) {
            return $attributes;
        }

        return [
            ...$attributes,
            'track_name' => $track->name,
            'artist_name' => ArtistCredits::display($track) ?? $attributes['artist_name'] ?? null,
            'isrc' => $track->isrc,
        ];
    }
}
