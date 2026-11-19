<?php

namespace App\Http\Controllers;

use App\Enums\AirplayOrigin;
use App\Http\Requests\AirplayRequest;
use App\Models\Airplay;
use App\Models\AirplayFinding;
use App\Models\AirplaySearch;
use App\Models\Release;
use App\Models\Track;
use App\Services\Airplay\AirplayResearch;
use App\Services\Airplay\ResearchState;
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
        $researched = AirplaySearch::query()->whereNotNull('airplay_id')->where('succeeded', true)
            ->selectRaw('airplay_id, MAX(created_at) as researched_at')->groupBy('airplay_id')->toBase()->pluck('researched_at', 'airplay_id');

        return Inertia::render('airplays/index', [
            'findings' => AirplayFinding::query()->where('status', AirplayFinding::NEW)->with(['track:id,name', 'airplay:id,track_name,station'])
                ->orderByRaw("verification = 'confirmed' desc")->orderByDesc('is_radio')->latest()->limit(200)->get()
                ->map(fn (AirplayFinding $finding): array => [
                    ...$finding->only(['id', 'is_radio', 'station', 'url', 'title', 'description', 'domain', 'query', 'airplay_id', 'verification', 'page_title', 'show', 'excerpt']),
                    'track' => $finding->track->name ?? $finding->airplay?->track_name,
                    'airplay_station' => $finding->airplay_id === null ? null : ($finding->airplay->station ?? __('Unidentified')),
                ]),
            'searches' => AirplaySearch::query()->with(['track:id,name', 'airplay:id,track_name,station'])->latest('id')->limit(100)->get()
                ->map(fn (AirplaySearch $entry): array => [
                    ...$entry->only(['id', 'query', 'region', 'succeeded', 'new_findings', 'error', 'airplay_id']),
                    'track' => $entry->track->name ?? $entry->airplay?->track_name,
                    'station' => $entry->airplay?->station,
                    'results' => $entry->results ?? [],
                    'created_at' => $entry->created_at?->toDateTimeString(),
                ]),
            'trackResearch' => Track::query()->whereNotNull('research_state')->orderByRaw("research_state = 'running' desc")->orderBy('research_state_at')
                ->get(['id', 'name', 'research_state', 'research_step', 'research_state_at'])
                ->map(fn (Track $track): array => [
                    'id' => $track->id,
                    'name' => $track->name,
                    'state' => ResearchState::visible($track->research_state, $track->research_state_at),
                    'step' => $track->research_step,
                ])->whereNotNull('state')->values(),
            'releases' => Release::query()->orderBy('name')->get(['id', 'name']),
            'staleCount' => $research->stale(Track::query())->count(),
            'staleDays' => AirplayResearch::STALE_DAYS,
            // ponytail: the whole list in one page; paginate when a label has thousands of plays.
            'airplays' => Airplay::query()->orderByRaw('station is null')->orderBy('station')->orderByDesc('period_end')->orderBy('track_name')->get()
                ->map(fn (Airplay $airplay): array => [
                    ...$airplay->only(['id', 'origin', 'track_id', 'track_name', 'artist_name', 'isrc', 'station', 'media_type', 'region', 'society', 'amount', 'notes', 'data_import_id']),
                    'period_start' => $airplay->period_start?->toDateString(),
                    'period_end' => $airplay->period_end?->toDateString(),
                    'links' => $airplay->links ?? [],
                    'search_query' => $airplay->searchQuery(),
                    'researched_at' => $researched[$airplay->id] ?? null,
                    'research_state' => ResearchState::visible($airplay->research_state, $airplay->research_state_at),
                    'research_step' => $airplay->research_step,
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

        $query = $query ?: $airplay->searchQuery();
        $log = ['track_id' => $airplay->track_id, 'airplay_id' => $airplay->id, 'query' => $query, 'region' => $airplay->region ?? $search->markets()[0]];

        try {
            $results = $search->search($query, region: $airplay->region);
            AirplaySearch::query()->create([...$log, 'succeeded' => true, 'results' => $results]);

            return response()->json(['results' => $results]);
        } catch (RuntimeException $exception) {
            report($exception);
            AirplaySearch::query()->create([...$log, 'succeeded' => false, 'error' => mb_substr($exception->getMessage(), 0, 500)]);

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
        $track = isset($attributes['track_id']) ? Track::query()->with(['artist:id,name', 'artists:id,name'])->whereKey($attributes['track_id'])->first() : null;

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
