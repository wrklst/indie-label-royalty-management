<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\TrackRequest;
use App\Models\Airplay;
use App\Models\AirplayFinding;
use App\Models\AirplaySearch;
use App\Models\Artist;
use App\Models\Contract;
use App\Models\Release;
use App\Models\Track;
use App\Models\Transaction;
use App\Services\Airplay\ResearchState;
use App\Services\Airplay\WebSearch;
use App\Services\Imports\Identifiers;
use App\Services\Releases\ArtistCredits;
use App\Services\Releases\PlayableAudio;
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
            ->with([
                'artist:id,name', 'artists:id,name', 'contract:id,name', 'release:id,name,catalog_number,contract_id,artist_id', 'release.contract:id,name',
                'release.artist:id,name', 'release.artists:id,name', 'release.cover:id,release_id',
            ])
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('isrc', 'like', '%'.str_replace('-', '', $search).'%')))
            // A recording on several releases is listed once, by its first entry;
            // within one release every entry of that release is listed.
            ->when($request->integer('release'), fn ($query, int $release) => $query->where('release_id', $release), fn ($query) => $query
                ->where(fn ($query) => $query->whereNull('isrc')->orWhereRaw('tracks.id = (select min(entries.id) from tracks as entries where entries.label_id = tracks.label_id and entries.isrc = tracks.isrc)')))
            ->tap($sorting)
            ->paginate(50)
            ->withQueryString();
        $audio = PlayableAudio::forTracks($tracks->getCollection());
        $recordings = $request->integer('release') ? collect() : Track::query()
            ->whereIn('isrc', $tracks->getCollection()->pluck('isrc')->filter())
            ->with(['release:id,name,contract_id'])->orderBy('id')->get()->groupBy('isrc');

        $tracks = $tracks->through(fn (Track $track): array => [
            ...$track->only(['id', 'name', 'artist_id', 'contract_id', 'isrc', 'iswc', 'track_number', 'duration', 'mechanicals_rate', 'ppd_rate', 'release_id', 'version']),
            'artist' => $track->artist?->name,
            'release' => $track->release?->only(['id', 'name', 'catalog_number']),
            'releases' => ($recordings->get($track->isrc) ?? collect([$track]))->map(fn (Track $entry): ?array => $entry->release?->only(['id', 'name']))->filter()->unique('id')->values(),
            // Each entry has its own contract; one without is named here, as its row is not listed.
            'without_contract_on' => ($recordings->get($track->isrc) ?? collect())
                ->filter(fn (Track $entry): bool => $entry->id !== $track->id && $entry->contract_id === null && $entry->release?->contract_id === null)
                ->map(fn (Track $entry): string => $entry->release->name ?? $entry->name)->values(),
            'audio_file_id' => $audio->get($track->id)?->id,
            'credited_artist' => ArtistCredits::display($track) ?? ($track->release ? ArtistCredits::display($track->release) : null),
            'cover_file_id' => $track->release?->cover?->id,
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

    /**
     * Everything known about one track: where it belongs, what it earned and where it was played.
     */
    public function show(Track $track, WebSearch $search): Response
    {
        $track->load([
            'artist:id,name', 'artists:id,name', 'contract:id,name', 'release:id,name,catalog_number,contract_id', 'release.contract:id,name',
            'release.cover:id,release_id',
        ]);

        $ids = $track->recordingIds();
        $income = Transaction::query()->whereIn('track_id', $ids)->where('type', TransactionType::Income);

        return Inertia::render('tracks/show', [
            'track' => [
                ...$track->only(['id', 'name', 'version', 'isrc', 'iswc', 'track_number', 'duration', 'genre', 'bpm', 'musical_key']),
                'credited_artist' => ArtistCredits::display($track),
                // The main artist is not among the further credits, so it is put in front of them.
                'artists' => collect([$track->artist])->concat($track->artists)->filter()->unique('id')
                    ->map(fn (Artist $artist): array => $artist->only(['id', 'name']))->values(),
                'release' => $track->release?->only(['id', 'name', 'catalog_number']),
                'cover_file_id' => $track->release?->cover?->id,
                'audio_file_id' => PlayableAudio::forTracks(collect([$track]))->get($track->id)?->id,
                'contract' => $track->contract?->only(['id', 'name']),
                'inherited_contract' => $track->contract ? null : $track->release?->contract?->only(['id', 'name']),
                'researched_at' => $track->researched_at?->toDateTimeString(),
                'research_state' => ResearchState::visible($track->research_state, $track->research_state_at),
                'research_step' => $track->research_step,
            ],
            'income' => [
                'total' => (string) ((clone $income)->sum('converted_total') ?: '0'),
                'transactions' => (clone $income)->count(),
                'byRetailer' => (clone $income)->selectRaw('retailer, COUNT(*) as transactions, SUM(converted_total) as total')
                    ->groupBy('retailer')->orderByDesc('total')->limit(8)->toBase()->get()
                    ->map(fn ($row): array => ['label' => $row->retailer ?? 'Unknown', 'transactions' => (int) $row->transactions, 'total' => (string) $row->total]),
            ],
            'releases' => Track::query()->whereIn('id', $ids)->with('release:id,name,catalog_number')->orderBy('id')->get()
                ->filter(fn (Track $entry): bool => $entry->release !== null)
                ->map(fn (Track $entry): array => [
                    'track_id' => $entry->id,
                    ...$entry->release->only(['id', 'name', 'catalog_number']),
                    'income' => (string) (Transaction::query()->where('track_id', $entry->id)->where('type', TransactionType::Income)->sum('converted_total') ?: '0'),
                ])->values(),
            'airplays' => Airplay::query()->whereIn('track_id', $ids)->orderByRaw('station is null')->orderBy('station')->orderByDesc('period_end')->get()
                ->map(fn (Airplay $airplay): array => [
                    ...$airplay->only(['id', 'origin', 'station', 'media_type', 'region', 'society', 'amount', 'notes', 'data_import_id']),
                    'period_start' => $airplay->period_start?->toDateString(),
                    'period_end' => $airplay->period_end?->toDateString(),
                    'links' => $airplay->links ?? [],
                ]),
            'openFindings' => AirplayFinding::query()->whereIn('track_id', $ids)->where('status', AirplayFinding::NEW)->count(),
            'searches' => AirplaySearch::query()->whereIn('track_id', $ids)->latest('id')->limit(20)->get()
                ->map(fn (AirplaySearch $entry): array => [
                    ...$entry->only(['id', 'query', 'region', 'succeeded', 'new_findings', 'error']),
                    'results' => count($entry->results ?? []),
                    'created_at' => $entry->created_at?->toDateTimeString(),
                ]),
            'searchAvailable' => $search->isConfigured(),
            'platformLinks' => PlatformLinkController::props($track),
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
