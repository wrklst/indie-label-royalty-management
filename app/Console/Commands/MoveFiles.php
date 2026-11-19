<?php

namespace App\Console\Commands;

use App\Models\DataImport;
use App\Models\ReleaseFile;
use App\Support\CurrentLabel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('royalties:move-files
    {--to= : The disk to copy the files to, e.g. s3}
    {--dry-run : Only report what would be copied}')]
#[Description('Copy release files and statement files to another disk and point their records at it')]
class MoveFiles extends Command
{
    /**
     * Nothing is deleted: the files stay where they were, so a move can be
     * checked and, by moving back, undone. Run it again after an
     * interruption; files already on the target disk are left alone.
     */
    public function handle(CurrentLabel $currentLabel): int
    {
        $to = (string) $this->option('to');

        if ($to === '' || config("filesystems.disks.{$to}") === null) {
            $this->error('Pass --to with a disk from config/filesystems.php, e.g. --to=s3.');

            return self::FAILURE;
        }

        $currentLabel->set(null);
        $dryRun = (bool) $this->option('dry-run');
        $stats = ['moved' => 0, 'missing' => 0, 'failed' => 0];

        ReleaseFile::query()->where('disk', '!=', $to)->chunkById(100, function ($files) use ($to, $dryRun, &$stats): void {
            foreach ($files as $file) {
                $outcome = $this->copy($file->disk, $to, $file->path, $dryRun);

                if ($outcome === 'moved' && $file->preview_path !== null && $this->copy($file->disk, $to, $file->preview_path, $dryRun) === 'failed') {
                    $outcome = 'failed';
                }

                $stats[$outcome]++;

                if ($outcome !== 'moved') {
                    $this->warn("{$outcome}: release file {$file->id} ({$file->path})");
                } elseif (! $dryRun) {
                    // Resized images are made again on the new disk when they are next asked for.
                    $file->update(['disk' => $to]);
                }
            }
        });

        $statements = 0;

        DataImport::query()->whereNotNull('file_path')->chunkById(200, function ($imports) use ($to, $dryRun, &$statements, &$stats): void {
            foreach ($imports as $import) {
                if (Storage::disk($to)->exists($import->file_path) || ! Storage::disk('local')->exists($import->file_path)) {
                    continue;
                }

                $this->copy('local', $to, $import->file_path, $dryRun) === 'moved' ? $statements++ : $stats['failed']++;
            }
        });

        $this->info(($dryRun ? 'Would copy' : 'Copied')." {$stats['moved']} release file(s) and {$statements} statement file(s) to [{$to}]. Missing at the source: {$stats['missing']}. Failed: {$stats['failed']}.");

        if (! $dryRun && $stats['moved'] + $statements > 0) {
            $this->line('The originals were left in place. Remove them once you have checked the copies.');
        }

        return $stats['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Copy one file and check that all of it arrived.
     *
     * @return 'moved'|'missing'|'failed'
     */
    private function copy(string $from, string $to, string $path, bool $dryRun): string
    {
        $source = Storage::disk($from);
        $target = Storage::disk($to);

        if (! $source->exists($path)) {
            return 'missing';
        }

        if ($dryRun || ($target->exists($path) && $target->size($path) === $source->size($path))) {
            return 'moved';
        }

        try {
            $stream = $source->readStream($path);
            $stored = $stream !== null && $target->writeStream($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            return $stored && $target->size($path) === $source->size($path) ? 'moved' : 'failed';
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return 'failed';
        }
    }
}
