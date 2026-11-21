<?php

namespace App\Http\Controllers;

use App\Http\Requests\DataImportRequest;
use App\Models\PlayCount;
use App\Services\Imports\ImportException;
use App\Services\Imports\PlayCountImport;
use App\Support\CurrentLabel;
use App\Support\Platforms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What the platforms themselves say was played, uploaded as the files
 * their artist portals export.
 */
class PlayCountController extends Controller
{
    public function index(): Response
    {
        $unmatched = PlayCount::query()->whereNull('track_id')->toBase()->distinct()->orderBy('track_name')->get(['batch', 'track_name'])->groupBy('batch');

        return Inertia::render('play-counts/index', [
            'files' => PlayCount::query()->toBase()
                ->selectRaw('batch, MAX(source_file) as source_file, MIN(period_start) as starts, MAX(period_end) as ends, COUNT(*) as counts, COUNT(track_id) as matched, SUM(plays) as plays, MAX(created_at) as created_at')
                ->groupBy('batch')->orderByDesc('batch')->get()
                ->map(fn ($file): array => [
                    'batch' => $file->batch,
                    'source_file' => $file->source_file,
                    'platforms' => PlayCount::query()->where('batch', $file->batch)->distinct()->orderBy('platform')->pluck('platform')
                        ->map(fn (string $key): array => ['key' => $key, 'name' => Platforms::names()[$key] ?? $key])->all(),
                    'starts' => substr((string) $file->starts, 0, 10),
                    'ends' => substr((string) $file->ends, 0, 10),
                    'counts' => (int) $file->counts,
                    'matched' => (int) $file->matched,
                    'plays' => (int) $file->plays,
                    'created_at' => (string) $file->created_at,
                    'unmatched' => ($unmatched[$file->batch] ?? collect())->pluck('track_name')->take(30)->all(),
                ]),
            'platforms' => collect(Platforms::names())->map(fn (string $name, string $key): array => ['value' => $key, 'label' => $name])->values(),
        ]);
    }

    public function store(Request $request, PlayCountImport $import, CurrentLabel $currentLabel): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.DataImportRequest::MAX_KILOBYTES, 'extensions:'.implode(',', DataImportRequest::EXTENSIONS)],
            'platform' => ['nullable', Rule::in(array_keys(Platforms::names()))],
            'from' => ['nullable', 'date', 'required_with:to'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        try {
            $result = $import->import(
                $currentLabel->get(), $request->file('file')->getRealPath(), $request->file('file')->getClientOriginalName(),
                $validated['platform'] ?? null, $validated['from'] ?? null, $validated['to'] ?? null,
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

    /**
     * Take back everything one uploaded file brought.
     */
    public function destroy(string $batch): RedirectResponse
    {
        PlayCount::query()->where('batch', $batch)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Play counts of the file deleted.')]);

        return to_route('play-counts.index');
    }

    /**
     * Download the empty template, for figures typed off a portal that exports nothing.
     */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'w');

            if ($stream === false) {
                throw new RuntimeException('The download stream could not be opened.');
            }

            fputcsv($stream, PlayCountImport::COLUMNS, ',', '"', '');
            fputcsv($stream, ['Tidal', 'DEXXX2600001', 'Track name', 'Artist name', '2026-01-01', '2026-03-31', '1250', ''], ',', '"', '');
            fclose($stream);
        }, 'play-count-template.csv', ['Content-Type' => 'text/csv']);
    }
}
