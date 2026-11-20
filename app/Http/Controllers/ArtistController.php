<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\ArtistRequest;
use App\Models\Artist;
use App\Models\Release;
use App\Models\Track;
use App\Models\Transaction;
use App\Services\Releases\PlayableAudio;
use App\Support\Decimal;
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
            ->withCount(['releases', 'aliases'])
            // Tracks the artist is on in any role, as on the artist's own page.
            ->selectSub(Track::query()->selectRaw('count(*)')->where(fn ($query) => $query
                ->whereColumn('tracks.artist_id', 'artists.id')
                ->orWhereExists(fn ($query) => $query->from('artist_track')
                    ->whereColumn('artist_track.track_id', 'tracks.id')->whereColumn('artist_track.artist_id', 'artists.id'))), 'tracks_count')
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

        $releases = Release::query()->where(fn ($query) => $query->where('artist_id', $artist->id)->orWhereHas('artists', fn ($query) => $query->whereKey($artist->id)))
            ->orderByDesc('released_on')->orderBy('name')->get(['id', 'name', 'catalog_number', 'released_on', 'artist_id']);
        $albums = PlayableAudio::albums($releases);

        // Everything booked on the artist's releases, plus what their tracks earned on releases
        // of others, such as compilations. Each transaction counts once.
        $booked = fn () => Transaction::query()->where(fn ($query) => $query
            ->whereIn('release_id', $releases->modelKeys())->orWhereIn('track_id', $tracks->modelKeys()));
        $byType = $booked()->selectRaw('type, SUM(converted_total) as total, SUM(quantity) as quantity, COUNT(*) as transactions')
            ->groupBy('type')->toBase()->get()->keyBy('type');
        $incomeByRelease = Transaction::query()->where('type', TransactionType::Income)->whereIn('release_id', $releases->modelKeys())
            ->selectRaw('release_id, SUM(converted_total) as total')->groupBy('release_id')->toBase()->pluck('total', 'release_id');
        $incomeByTrack = Transaction::query()->where('type', TransactionType::Income)->whereIn('track_id', $tracks->modelKeys())
            ->selectRaw('track_id, SUM(converted_total) as total')->groupBy('track_id')->toBase()->pluck('total', 'track_id');
        $income = Decimal::parse($byType['income']->total ?? 0) ?? '0';
        $expense = Decimal::parse($byType['expense']->total ?? 0) ?? '0';

        return Inertia::render('artists/show', [
            'artist' => $artist->only(['id', 'name']),
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
                    'income' => Decimal::parse($incomeByRelease[$release->id] ?? 0) ?? '0',
                ]),
            'tracks' => $tracks->map(fn (Track $track): array => [
                ...$track->only(['id', 'name', 'isrc']),
                'release' => $track->release?->only(['id', 'name']),
                'airplays_count' => $track->airplays_count,
                'income' => Decimal::parse($incomeByTrack[$track->id] ?? 0) ?? '0',
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
