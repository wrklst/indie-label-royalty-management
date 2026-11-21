<?php

namespace App\Http\Controllers;

use App\Http\Requests\DataImportRequest;
use App\Models\PlayCount;
use App\Models\Transaction;
use App\Services\Imports\ImportException;
use App\Services\Imports\PlayCountImport;
use App\Services\Reports\IncomeReport;
use App\Support\CurrentLabel;
use App\Support\Platforms;
use App\Support\Services;
use App\Support\Sorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the platforms themselves say was played, uploaded as the files
 * their artist portals export.
 */
class PlayCountController extends Controller
{
    /** The data export of Zebralution's analytics; it answers to whoever is signed in there. */
    private const string ZEBRALUTION_EXPORT = 'https://musicanalytics.zebralution.tools/maapidata/Export';

    /** How many names of tracks the catalog does not have are listed under a file. */
    private const int UNMATCHED_SHOWN = 30;

    public function index(Request $request, CurrentLabel $currentLabel): Response
    {
        $sorting = Sorting::for($request, [
            'file' => 'source_file',
            'platform' => 'first_platform',
            'covers' => 'starts',
            'rows' => 'counts',
            'plays' => 'plays',
            'created_at' => 'created_at',
        ], fn (Builder $query) => $query->orderByDesc('batch'));

        $files = PlayCount::query()
            ->selectRaw('batch, MAX(source_file) as source_file, MIN(platform) as first_platform, MIN(period_start) as starts, MAX(period_end) as ends, COUNT(*) as counts, COUNT(track_id) as matched, SUM(plays) as plays, MAX(created_at) as created_at')
            ->groupBy('batch')->tap($sorting)->orderByDesc('batch')->paginate(50)->withQueryString();
        $batches = $files->getCollection()->pluck('batch')->all();
        $unmatchedCounts = PlayCount::query()->whereNull('track_id')->whereIn('batch', $batches)->toBase()
            ->selectRaw('batch, COUNT(DISTINCT track_name) as names')->groupBy('batch')->pluck('names', 'batch');
        $platforms = PlayCount::query()->whereIn('batch', $batches)->distinct()->orderBy('platform')->get(['batch', 'platform'])->groupBy('batch');
        // The first names of each file, ranked in the database so that the page costs one query.
        $names = DB::query()->fromSub(
            DB::query()->fromSub(
                PlayCount::query()->whereNull('track_id')->whereIn('batch', $batches)->toBase()->select('batch', 'track_name')->distinct(),
                'distinct_names',
            )->selectRaw('batch, track_name, ROW_NUMBER() OVER (PARTITION BY batch ORDER BY track_name) as position'),
            'ranked_names',
        )->where('position', '<=', self::UNMATCHED_SHOWN)->orderBy('position')->get(['batch', 'track_name'])->groupBy('batch');

        // The week before the last counted day is fetched again: the latest days are still filling up.
        $latest = PlayCount::query()->max('period_end');
        $from = $latest === null ? now()->subYear() : Carbon::parse($latest)->subWeek();

        return Inertia::render('play-counts/index', [
            'report' => IncomeReport::plays(PlayCount::query(), Transaction::query()),
            'tracks' => IncomeReport::playMovers(PlayCount::query(), 'track_id'),
            'releases' => IncomeReport::playMovers(PlayCount::query(), 'release_id'),
            'zebralutionExport' => ! $currentLabel->get()->usesService(Services::ZEBRALUTION) ? null : self::ZEBRALUTION_EXPORT.'?'.http_build_query([
                'startdate' => $from->toDateString(), 'enddate' => now()->subDay()->toDateString(),
                'resulttype' => 'streams', 'groupby_export' => 'track,dsp,date', 'fields' => 'streams',
            ]),
            'filters' => $sorting->toArray(),
            'files' => $files
                ->through(fn (PlayCount $file): array => [
                    'batch' => $file->batch,
                    'source_file' => $file->source_file,
                    'platforms' => ($platforms[$file->batch] ?? collect())->pluck('platform')
                        ->map(fn (string $key): array => ['key' => $key, 'name' => Platforms::names()[$key] ?? Platforms::of(null)['name']])->values()->all(),
                    'starts' => substr((string) $file->getAttribute('starts'), 0, 10),
                    'ends' => substr((string) $file->getAttribute('ends'), 0, 10),
                    'counts' => (int) $file->getAttribute('counts'),
                    'matched' => (int) $file->getAttribute('matched'),
                    'plays' => (int) $file->plays,
                    'created_at' => Carbon::parse($file->created_at)->toIso8601String(),
                    'unmatched' => [
                        'names' => ($names[$file->batch] ?? collect())->pluck('track_name')->values()->all(),
                        'total' => (int) ($unmatchedCounts[$file->batch] ?? 0),
                    ],
                ]),
        ]);
    }

    public function store(Request $request, PlayCountImport $import, CurrentLabel $currentLabel): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.DataImportRequest::MAX_KILOBYTES, 'extensions:'.implode(',', DataImportRequest::EXTENSIONS)],
        ]);

        try {
            $result = $import->import(
                $currentLabel->get(), $request->file('file')->getRealPath(), $request->file('file')->getClientOriginalName(),
            );
        } catch (ImportException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $missing = $result['counts'] - $result['matched'];

        Inertia::flash('toast', [
            'type' => $missing === 0 ? 'success' : 'warning',
            'message' => $missing === 0
                ? __(':count play counts imported.', ['count' => $result['counts']])
                : __(':count play counts imported, :missing of them for tracks the catalog does not have under that name.', ['count' => $result['counts'], 'missing' => $missing]),
        ]);

        return to_route('play-counts.index');
    }

    public function destroy(string $batch): RedirectResponse
    {
        PlayCount::query()->where('batch', $batch)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Play counts of the file deleted.')]);

        return to_route('play-counts.index');
    }
}
