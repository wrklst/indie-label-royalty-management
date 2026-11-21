<?php

namespace App\Http\Controllers;

use App\Enums\ImportStatus;
use App\Enums\RoyaltyTool;
use App\Http\Requests\DataImportRequest;
use App\Models\DataImport;
use App\Models\Item;
use App\Models\Transaction;
use App\Services\Imports\DuplicateFinder;
use App\Services\Imports\ImporterRegistry;
use App\Services\Imports\Importers\TemplateImporter;
use App\Services\Imports\ImportException;
use App\Services\Imports\ImportRunner;
use App\Services\Imports\Readers\ReaderFactory;
use App\Services\Reports\IncomeReport;
use App\Services\Royalties\MoneyFlow;
use App\Support\CurrentLabel;
use App\Support\Filters;
use App\Support\PageSize;
use App\Support\Services;
use App\Support\Sorting;
use App\Support\WorkingCopy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataImportController extends Controller
{
    public function index(Request $request, ImporterRegistry $importers): Response
    {
        $sorting = Sorting::for($request, [
            'name' => 'name',
            'importer' => 'importer',
            'status' => 'status',
            'default_paid_timestamp' => Sorting::nullsLast('default_paid_timestamp'),
            'rows_count' => 'rows_count',
            'transactions_count' => 'transactions_count',
            'duplicates_count' => 'duplicates_count',
            'total' => 'total',
            'created_at' => 'created_at',
        ], fn (Builder $query) => $query->latest('id'));

        $names = collect($importers->describe())->pluck('name', 'key');

        return Inertia::render('data-imports/index', [
            'imports' => DataImport::query()
                ->with('user:id,name')
                ->when(trim(Filters::text($request, 'search')), fn ($query, string $search) => $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")->orWhere('original_filename', 'like', "%{$search}%")->orWhere('tags', 'like', "%{$search}%")))
                ->when(Filters::text($request, 'source'), fn ($query, string $source) => $query->where('importer', $source))
                ->when(ImportStatus::tryFrom(Filters::text($request, 'status')), fn ($query, ImportStatus $status) => $query->where('status', $status))
                ->tap($sorting)
                ->paginate(PageSize::MAIN)
                ->withQueryString()
                ->through(fn (DataImport $import): array => [
                    ...$import->only(['id', 'name', 'importer', 'original_filename', 'rows_count', 'transactions_count', 'duplicates_count', 'errors_count', 'needs_cleanup', 'total', 'tags']),
                    'importer_name' => $names[$import->importer] ?? RoyaltyTool::importerName($import->importer) ?? $import->importer,
                    'status' => $import->status->value,
                    'default_paid_timestamp' => $import->default_paid_timestamp?->toDateString(),
                    'created_at' => $import->created_at->toIso8601String(),
                    'user' => $import->user?->name,
                ]),
            'filters' => [
                'search' => Filters::text($request, 'search'),
                'source' => Filters::text($request, 'source'),
                'status' => ImportStatus::tryFrom(Filters::text($request, 'status'))?->value,
                ...$sorting->toArray(),
            ],
            'sourceOptions' => DataImport::query()->distinct()->orderBy('importer')->pluck('importer')
                ->map(fn (string $importer): array => ['id' => $importer, 'name' => $names[$importer] ?? RoyaltyTool::importerName($importer) ?? $importer])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
        ]);
    }

    public function create(ImporterRegistry $importers, CurrentLabel $currentLabel): Response
    {
        return Inertia::render('data-imports/create', [
            // The layouts of a service the label does not work with are not offered; a file in one is still recognised.
            'sources' => array_values(array_filter($importers->sources(), fn (array $source): bool => ! Services::has($source['key']) || $currentLabel->get()->usesService($source['key']))),
            'currency' => $currentLabel->get()->currency,
        ]);
    }

    public function store(DataImportRequest $request, ImportRunner $runner, ImporterRegistry $importers, ReaderFactory $readers, CurrentLabel $currentLabel): RedirectResponse
    {
        $label = $currentLabel->get();
        $file = $request->statement($label->id);
        $hash = hash_file('sha256', $file->path());

        try {
            $importer = $importers->resolve($request->validated('importer'), $readers->open($file->path()));
        } catch (ImportException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        if ($importer->requiresPaidDate() && $request->validated('default_paid_timestamp') === null) {
            throw ValidationException::withMessages([
                'default_paid_timestamp' => "{$importer->sourceName()} statements have no payment date. Enter the day the money arrived so it lands in the right period.",
            ]);
        }

        $import = DataImport::query()->create([
            'user_id' => $request->user()->id,
            'importer' => $importer->key(),
            'name' => $request->validated('name') ?? pathinfo($file->name, PATHINFO_FILENAME),
            'status' => ImportStatus::Pending,
            'original_filename' => $file->name,
            'file_path' => $file->storeAs("imports/{$label->id}", Str::random(40).'.'.$file->extension(), WorkingCopy::disk()),
            'file_hash' => $hash,
            'default_timestamp' => $request->validated('default_timestamp'),
            'replace_timestamp' => $request->boolean('replace_timestamp'),
            'default_paid_timestamp' => $request->validated('default_paid_timestamp'),
            'replace_paid_timestamp' => $request->boolean('replace_paid_timestamp'),
            'tags' => array_values(array_filter(array_map('trim', explode(',', (string) $request->validated('tags'))))),
            'options' => [
                'conversion_rate' => $request->validated('conversion_rate'),
                'skip_duplicates' => $request->boolean('skip_duplicates', true),
            ],
        ]);

        $import = $runner->run($import, $label);

        Inertia::flash('toast', $this->outcome($import));

        return to_route('data-imports.show', $import);
    }

    /**
     * Import a file that was held for possible duplicates, with a decision for each of them.
     */
    public function resolve(Request $request, DataImport $dataImport, ImportRunner $runner, CurrentLabel $currentLabel): RedirectResponse
    {
        abort_unless($dataImport->status === ImportStatus::Review, 404);

        $keys = array_column($dataImport->metadata['duplicates'] ?? [], 'key');
        $decisions = $request->validate([
            'decisions' => ['required', 'array:'.implode(',', $keys)],
            'decisions.*' => ['required', Rule::in([DuplicateFinder::REPLACE, DuplicateFinder::SKIP, DuplicateFinder::BOTH])],
        ])['decisions'];

        if (array_diff($keys, array_keys($decisions)) !== []) {
            throw ValidationException::withMessages(['decisions' => __('Decide for every possible duplicate.')]);
        }

        $dataImport->update([
            'status' => ImportStatus::Pending,
            'options' => [...($dataImport->options ?? []), 'duplicates' => [...($dataImport->options['duplicates'] ?? []), ...$decisions]],
        ]);

        Inertia::flash('toast', $this->outcome($runner->run($dataImport, $currentLabel->get())));

        return to_route('data-imports.show', $dataImport);
    }

    /**
     * @return array{type: string, message: string}
     */
    private function outcome(DataImport $import): array
    {
        return match ($import->status) {
            ImportStatus::Imported => ['type' => 'success', 'message' => __(':count transactions imported.', ['count' => $import->transactions_count])],
            ImportStatus::Review => ['type' => 'warning', 'message' => __('Possible duplicates found. Nothing was imported yet: decide below.')],
            default => ['type' => 'error', 'message' => __('The import failed. Nothing was saved.')],
        };
    }

    public function show(DataImport $dataImport, ImporterRegistry $importers): Response
    {
        $transactions = Transaction::query()->where('data_import_id', $dataImport->id);

        return Inertia::render('data-imports/show', [
            'import' => [
                ...$dataImport->only(['id', 'name', 'importer', 'original_filename', 'rows_count', 'transactions_count', 'duplicates_count', 'errors_count', 'created_releases_count', 'created_tracks_count', 'needs_cleanup', 'total', 'errors', 'metadata', 'tags', 'replace_timestamp', 'replace_paid_timestamp']),
                'importer_name' => $importers->find($dataImport->importer)?->name() ?? RoyaltyTool::importerName($dataImport->importer) ?? $dataImport->importer,
                'status' => $dataImport->status->value,
                'default_timestamp' => $dataImport->default_timestamp?->toDateString(),
                'default_paid_timestamp' => $dataImport->default_paid_timestamp?->toDateString(),
                'created_at' => $dataImport->created_at->toIso8601String(),
                'user' => $dataImport->user?->name,
            ],
            'flow' => MoneyFlow::of(clone $transactions),
            'unassignedItems' => Item::query()->where('data_import_id', $dataImport->id)->whereNull('release_id')->count(),
            'report' => IncomeReport::of(clone $transactions),
        ]);
    }

    public function destroy(DataImport $dataImport, ImportRunner $runner): RedirectResponse
    {
        $runner->delete($dataImport);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Import and its transactions deleted.')]);

        return to_route('data-imports.index');
    }

    /**
     * Download the empty spreadsheet template.
     */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'w');

            if ($stream === false) {
                throw new RuntimeException('The download stream could not be opened.');
            }

            fputcsv($stream, TemplateImporter::COLUMNS, ',', '"', '');
            fputcsv($stream, ['2026-03-31', '2026-04-15', 'income', '125.50', 'EUR', '', 'Artist name', 'Release name', 'Track name', 'CAT001', 'DEXXX2600001', '4000000000000', '', '', 'Sync fee', 'Publisher', 'Master Use License', 'Sync', '1', '', 'Publisher', '', 'DE'], ',', '"', '');
            fclose($stream);
        }, 'transaction-template.csv', ['Content-Type' => 'text/csv']);
    }
}
