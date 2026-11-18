<?php

namespace App\Http\Controllers;

use App\Enums\ImportStatus;
use App\Enums\RoyaltyTool;
use App\Http\Requests\DataImportRequest;
use App\Models\DataImport;
use App\Models\Item;
use App\Models\Transaction;
use App\Services\Imports\ImporterRegistry;
use App\Services\Imports\Importers\TemplateImporter;
use App\Services\Imports\ImportException;
use App\Services\Imports\ImportRunner;
use App\Services\Imports\Readers\ReaderFactory;
use App\Support\CurrentLabel;
use App\Support\Sorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'transactions_count' => 'transactions_count',
            'duplicates_count' => 'duplicates_count',
            'total' => 'total',
            'created_at' => 'created_at',
        ], fn (Builder $query) => $query->latest('id'));

        $names = collect($importers->describe())->pluck('name', 'key');

        return Inertia::render('data-imports/index', [
            'imports' => DataImport::query()
                ->with('user:id,name')
                ->tap($sorting)
                ->paginate(50)
                ->withQueryString()
                ->through(fn (DataImport $import): array => [
                    ...$import->only(['id', 'name', 'importer', 'original_filename', 'rows_count', 'transactions_count', 'duplicates_count', 'errors_count', 'needs_cleanup', 'total', 'tags']),
                    'importer_name' => $names[$import->importer] ?? RoyaltyTool::importerName($import->importer) ?? $import->importer,
                    'status' => $import->status->value,
                    'default_paid_timestamp' => $import->default_paid_timestamp?->toDateString(),
                    'created_at' => $import->created_at->toDateTimeString(),
                    'user' => $import->user?->name,
                ]),
            'filters' => $sorting->toArray(),
        ]);
    }

    public function create(ImporterRegistry $importers, CurrentLabel $currentLabel): Response
    {
        return Inertia::render('data-imports/create', [
            'sources' => $importers->sources(),
            'currency' => $currentLabel->get()->currency,
        ]);
    }

    public function store(DataImportRequest $request, ImportRunner $runner, ImporterRegistry $importers, ReaderFactory $readers, CurrentLabel $currentLabel): RedirectResponse
    {
        $label = $currentLabel->get();
        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());

        try {
            $importer = $importers->resolve($request->validated('importer'), $readers->open($file->getRealPath()));
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
            'name' => $request->validated('name') ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'status' => ImportStatus::Pending,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $file->store("imports/{$label->id}", 'local'),
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

        Inertia::flash('toast', $import->status === ImportStatus::Imported
            ? ['type' => 'success', 'message' => __(':count transactions imported.', ['count' => $import->transactions_count])]
            : ['type' => 'error', 'message' => __('The import failed. Nothing was saved.')]);

        return to_route('data-imports.show', $import);
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
                'created_at' => $dataImport->created_at->toDateTimeString(),
                'user' => $dataImport->user?->name,
            ],
            'unassignedItems' => Item::query()->where('data_import_id', $dataImport->id)->whereNull('release_id')->count(),
            'byRetailer' => (clone $transactions)->selectRaw('retailer, COUNT(*) as transactions, SUM(quantity) as quantity, SUM(converted_total) as total')
                ->groupBy('retailer')->orderByDesc('total')->toBase()->get()
                ->map(fn ($row): array => ['label' => $row->retailer ?? 'Unknown', 'transactions' => (int) $row->transactions, 'quantity' => (string) $row->quantity, 'total' => (string) $row->total]),
            'byFormat' => (clone $transactions)->selectRaw('format, COUNT(*) as transactions, SUM(quantity) as quantity, SUM(converted_total) as total')
                ->groupBy('format')->orderByDesc('total')->toBase()->get()
                ->map(fn ($row): array => ['label' => $row->format ?? 'Unknown', 'transactions' => (int) $row->transactions, 'quantity' => (string) $row->quantity, 'total' => (string) $row->total]),
            'byMonth' => (clone $transactions)->selectRaw('timestamp, COUNT(*) as transactions, SUM(quantity) as quantity, SUM(converted_total) as total')
                ->groupBy('timestamp')->orderBy('timestamp')->toBase()->get()
                ->map(fn ($row): array => ['label' => substr((string) $row->timestamp, 0, 7), 'transactions' => (int) $row->transactions, 'quantity' => (string) $row->quantity, 'total' => (string) $row->total]),
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
