<?php

namespace App\Console\Commands;

use App\Enums\ImportStatus;
use App\Models\DataImport;
use App\Models\Label;
use App\Services\Imports\ImporterRegistry;
use App\Services\Imports\ImportException;
use App\Services\Imports\ImportRunner;
use App\Services\Imports\Readers\ReaderFactory;
use App\Support\CurrentLabel;
use App\Support\WorkingCopy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('royalties:import
    {file : Path to the statement file}
    {--label= : Slug or id of the label to import into}
    {--importer=auto : Source or importer key, e.g. zebralution, bandcamp, audio-rights, template. Detected from the file by default}
    {--paid= : Paid date (Y-m-d) for sources without one}
    {--rate= : Conversion rate for lines in another currency}')]
#[Description('Import a statement file into a label, the same way the upload form does')]
class ImportStatement extends Command
{
    public function handle(ImporterRegistry $importers, ReaderFactory $readers, ImportRunner $runner, CurrentLabel $currentLabel): int
    {
        $path = (string) $this->argument('file');
        $label = Label::query()->where(ctype_digit((string) $this->option('label')) ? 'id' : 'slug', $this->option('label'))->first();

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        try {
            $importer = $importers->resolve((string) $this->option('importer'), $readers->open($path));
        } catch (ImportException $exception) {
            $this->error($exception->getMessage());
            $this->line('Available: '.implode(', ', $importers->choices()));

            return self::FAILURE;
        }

        if ($label === null) {
            $this->error('Label not found. Pass --label with a slug or id.');

            return self::FAILURE;
        }

        if ($importer->requiresPaidDate() && ! $this->option('paid')) {
            $this->error("{$importer->name()} statements carry no payment date. Pass --paid=YYYY-MM-DD.");

            return self::FAILURE;
        }

        $currentLabel->set($label);
        $stored = Storage::disk(WorkingCopy::disk())->putFile("imports/{$label->id}", $path);

        $import = $runner->run(DataImport::query()->create([
            'importer' => $importer->key(),
            'name' => pathinfo($path, PATHINFO_FILENAME),
            'status' => ImportStatus::Pending,
            'original_filename' => basename($path),
            'file_path' => $stored,
            'file_hash' => hash_file('sha256', $path),
            'default_paid_timestamp' => $this->option('paid'),
            'options' => ['conversion_rate' => $this->option('rate'), 'skip_duplicates' => true],
        ]), $label);

        if ($import->status === ImportStatus::Review) {
            $this->warn('Nothing was imported: '.count($import->metadata['duplicates'] ?? []).' possible duplicate(s) of existing bookings. Decide about them under Data imports → '.$import->name.'.');

            return self::FAILURE;
        }

        if ($import->status !== ImportStatus::Imported) {
            $this->error($import->errors[0]['message'] ?? 'The import failed.');

            return self::FAILURE;
        }

        $this->info("{$importer->name()}: {$import->transactions_count} transactions imported into {$label->name}, total {$import->total} {$label->currency}.");
        $this->line("{$import->duplicates_count} duplicates skipped, {$import->errors_count} lines with errors, {$import->created_releases_count} releases and {$import->created_tracks_count} tracks created.");

        return self::SUCCESS;
    }
}
