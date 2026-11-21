<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\ArtistRequest;
use App\Models\Artist;
use App\Models\Contract;
use App\Models\ContractSplit;
use App\Models\PlayCount;
use App\Models\Release;
use App\Models\Track;
use App\Models\Transaction;
use App\Services\Releases\PlayableAudio;
use App\Services\Reports\IncomeReport;
use App\Support\Decimal;
use App\Support\Options;
use App\Support\Sorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $onTrack = fn ($query) => $query
            ->whereColumn('tracks.artist_id', 'artists.id')
            ->orWhereExists(fn ($query) => $query->from('artist_track')
                ->whereColumn('artist_track.track_id', 'tracks.id')->whereColumn('artist_track.artist_id', 'artists.id'));

        $artists = Artist::query()
            ->with(['aliasOf:id,name', 'aliases:id,alias_of_id,name'])
            // An alias is listed with its artist; on its own only when asked for.
            ->unless($request->boolean('aliases'), fn ($query) => $query->whereNull('alias_of_id'))
            ->withCount('aliases')
            // Releases and tracks the artist is on in any role, as on the artist's own page.
            ->selectSub(Release::query()->selectRaw('count(*)')->where(fn ($query) => $query
                ->whereColumn('releases.artist_id', 'artists.id')
                ->orWhereExists(fn ($query) => $query->from('artist_release')
                    ->whereColumn('artist_release.release_id', 'releases.id')->whereColumn('artist_release.artist_id', 'artists.id'))
                ->orWhereExists(fn ($query) => $query->from('tracks')->whereColumn('tracks.release_id', 'releases.id')->where($onTrack))), 'releases_count')
            ->selectSub(Track::query()->selectRaw('count(*)')->where($onTrack), 'tracks_count')
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")->orWhereHas('aliases', fn ($query) => $query->where('name', 'like', "%{$search}%"))))
            ->tap($sorting)
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Artist $artist): array => [
                ...$artist->only(['id', 'name', 'alias_of_id', 'spotify_id', 'discogs_search_name', 'notes', 'releases_count', 'tracks_count', 'aliases_count']),
                'alias_of' => $artist->aliasOf?->name,
                'aliases' => $artist->aliases->sortBy('name')->pluck('name')->values(),
            ]);

        return Inertia::render('artists/index', [
            'artists' => $artists,
            'filters' => ['search' => $request->string('search')->value(), 'aliases' => $request->boolean('aliases'), ...$sorting->toArray()],
            'artistOptions' => Options::artists(),
        ]);
    }

    /**
     * The releases and tracks of one artist, and where the tracks were played.
     */
    public function show(Artist $artist): Response
    {
        $tracks = Track::query()->where(fn ($query) => $query->where('artist_id', $artist->id)->orWhereHas('artists', fn ($query) => $query->whereKey($artist->id)))
            ->with('release:id,name,contract_id')->withCount('airplays')->orderBy('name')->get();

        // Also the releases of others that carry one of the artist's tracks, such as a remix or a compilation.
        $releases = Release::query()->where(fn ($query) => $query->where('artist_id', $artist->id)->orWhereHas('artists', fn ($query) => $query->whereKey($artist->id))
            ->orWhereIn('id', $tracks->pluck('release_id')->filter()))
            ->orderByDesc('released_on')->orderBy('name')->get(['id', 'name', 'catalog_number', 'released_on', 'artist_id', 'contract_id']);
        $albums = PlayableAudio::albums($releases);
        $own = $releases->filter(fn (Release $release): bool => $release->artist_id === $artist->id || $release->artists->contains($artist))->modelKeys();
        // The contract of a track is its own, otherwise that of its release.
        $contracts = Contract::query()->with('splits.payee:id,name')->orderBy('name')
            ->findMany($tracks->toBase()->map(fn (Track $track): ?int => $track->contract_id ?? $track->release?->contract_id)->merge($releases->pluck('contract_id'))->filter()->unique());

        // Everything booked on the artist's releases, plus what their tracks earned on releases
        // of others, such as compilations. Each transaction counts once.
        $booked = fn () => Transaction::query()->where(fn ($query) => $query
            ->whereIn('release_id', $own)->orWhereIn('track_id', $tracks->modelKeys()));
        $byType = $booked()->selectRaw('type, SUM(converted_total) as total, SUM(quantity) as quantity, COUNT(*) as transactions')
            ->groupBy('type')->toBase()->get()->keyBy('type');
        $incomeByRelease = Transaction::query()->where('type', TransactionType::Income)->whereIn('release_id', $own)
            ->selectRaw('release_id, SUM(converted_total) as total')->groupBy('release_id')->toBase()->pluck('total', 'release_id');
        $incomeByTrack = Transaction::query()->where('type', TransactionType::Income)->whereIn('track_id', $tracks->modelKeys())
            ->selectRaw('track_id, SUM(converted_total) as total')->groupBy('track_id')->toBase()->pluck('total', 'track_id');
        $income = Decimal::parse($byType['income']->total ?? 0) ?? '0';
        $expense = Decimal::parse($byType['expense']->total ?? 0) ?? '0';

        return Inertia::render('artists/show', [
            'artist' => [
                ...$artist->only(['id', 'name']),
                'alias_of' => $artist->aliasOf?->only(['id', 'name']),
                'aliases' => $artist->aliases()->orderBy('name')->get(['id', 'name'])->map->only(['id', 'name']),
            ],
            'totals' => [
                'income' => $income,
                'expense' => $expense,
                'net' => Decimal::add($income, $expense),
                'units' => Decimal::parse($byType['income']->quantity ?? 0) ?? '0',
                'transactions' => (int) $byType->sum('transactions'),
                'airplays' => (int) $tracks->sum('airplays_count'),
            ],
            'releases' => $releases
                ->map(fn (Release $release): array => [
                    ...$release->only(['id', 'name', 'catalog_number']),
                    'player_tracks' => $albums[$release->id],
                    'released_on' => $release->released_on?->toDateString(),
                    // On a release of others: what the artist's tracks on it earned.
                    'income' => in_array($release->id, $own, true)
                        ? Decimal::parse($incomeByRelease[$release->id] ?? 0) ?? '0'
                        : $tracks->where('release_id', $release->id)->reduce(fn (string $sum, Track $track): string => Decimal::add($sum, Decimal::parse($incomeByTrack[$track->id] ?? 0) ?? '0'), '0'),
                ]),
            'report' => IncomeReport::of($booked(), PlayCount::query()->whereIn('track_id', $tracks->modelKeys())),
            'movers' => IncomeReport::movers($booked(), 'release_id'),
            'contracts' => $contracts->map(fn (Contract $contract): array => [
                ...$contract->only(['id', 'name']),
                'splits' => $contract->splits->map(fn (ContractSplit $split): array => [
                    'payee' => $split->payee?->only(['id', 'name']),
                    'split_amount' => $split->split_amount,
                ])->values(),
            ])->values(),
            // A recording on several releases has one track per release; it is listed once.
            'tracks' => $tracks->toBase()->groupBy(fn (Track $track): string => $track->isrc ?? "track {$track->id}")->values()
                ->map(fn (Collection $entries): array => [
                    ...$entries->first()->only(['id', 'name', 'isrc']),
                    'releases' => $entries->map(fn (Track $track): ?array => $track->release?->only(['id', 'name']))->filter()->unique('id')->values(),
                    'airplays_count' => (int) $entries->sum('airplays_count'),
                    'income' => $entries->reduce(fn (string $sum, Track $track): string => Decimal::add($sum, Decimal::parse($incomeByTrack[$track->id] ?? 0) ?? '0'), '0'),
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

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Artist saved.')]);

        return back();
    }

    public function destroy(Artist $artist): RedirectResponse
    {
        $artist->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Artist deleted.')]);

        return back();
    }
}
