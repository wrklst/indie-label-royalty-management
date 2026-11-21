<?php

namespace App\Http\Controllers;

use App\Http\Requests\DataImportRequest;
use App\Models\PlayCount;
use App\Models\Transaction;
use App\Services\Imports\ImportException;
use App\Services\Imports\PlayCountImport;
use App\Services\Reports\IncomeReport;
use App\Support\CurrentLabel;
use App\Support\Distributors;
use App\Support\Platforms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function index(CurrentLabel $currentLabel): Response
    {
        $unmatched = PlayCount::query()->whereNull('track_id')->toBase()->distinct()->orderBy('track_name')->get(['batch', 'track_name'])->groupBy('batch');

        // The week before the last counted day is fetched again: the latest days are still filling up.
        $latest = PlayCount::query()->max('period_end');
        $from = $latest === null ? now()->subYear() : Carbon::parse($latest)->subWeek();

        return Inertia::render('play-counts/index', [
            'report' => IncomeReport::plays(PlayCount::query(), Transaction::query()),
            'tracks' => IncomeReport::playMovers(PlayCount::query(), 'track_id'),
            'releases' => IncomeReport::playMovers(PlayCount::query(), 'release_id'),
            'zebralutionExport' => ! $currentLabel->get()->usesDistributor(Distributors::ZEBRALUTION) ? null : self::ZEBRALUTION_EXPORT.'?'.http_build_query([
                'startdate' => $from->toDateString(), 'enddate' => now()->subDay()->toDateString(),
                'resulttype' => 'streams', 'groupby_export' => 'track,dsp,date', 'fields' => 'streams',
            ]),
            'files' => PlayCount::query()->toBase()
                ->selectRaw('batch, MAX(source_file) as source_file, MIN(period_start) as starts, MAX(period_end) as ends, COUNT(*) as counts, COUNT(track_id) as matched, SUM(plays) as plays, MAX(created_at) as created_at')
                ->groupBy('batch')->orderByDesc('batch')->get()
                ->map(fn ($file): array => [
                    'batch' => $file->batch,
                    'source_file' => $file->source_file,
                    'platforms' => PlayCount::query()->where('batch', $file->batch)->distinct()->orderBy('platform')->pluck('platform')
                        ->map(fn (string $key): array => ['key' => $key, 'name' => Platforms::names()[$key] ?? Platforms::of(null)['name']])->all(),
                    'starts' => substr((string) $file->starts, 0, 10),
                    'ends' => substr((string) $file->ends, 0, 10),
                    'counts' => (int) $file->counts,
                    'matched' => (int) $file->matched,
                    'plays' => (int) $file->plays,
                    'created_at' => (string) $file->created_at,
                    'unmatched' => ($unmatched[$file->batch] ?? collect())->pluck('track_name')->take(30)->all(),
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
            'type' => $missing === 0 ? 'success' : 'error',
            'message' => $missing === 0
                ? __(':count play counts imported.', ['count' => $result['counts']])
                : __(':count play counts imported, :missing of them for tracks the catalogue does not have under that name.', ['count' => $result['counts'], 'missing' => $missing]),
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
