<?php

namespace App\Console\Commands;

use App\Enums\AudioPreviewStatus;
use App\Enums\ReleaseFileKind;
use App\Models\Label;
use App\Models\ReleaseFile;
use App\Services\Releases\AudioPreviews;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('royalties:generate-previews
    {--label= : Only this label (slug or id)}
    {--release= : Only this release (id)}
    {--force : Convert again what already has a playback copy}')]
#[Description('Make the MP3 playback copies of audio masters that have none yet')]
class GenerateAudioPreviews extends Command
{
    public function handle(AudioPreviews $previews): int
    {
        $query = ReleaseFile::query()->withoutGlobalScopes()->where('kind', ReleaseFileKind::Audio);

        if ($this->option('label') !== null) {
            $label = Label::query()->where(ctype_digit((string) $this->option('label')) ? 'id' : 'slug', $this->option('label'))->first();

            if ($label === null) {
                $this->error("There is no label “{$this->option('label')}”.");

                return self::FAILURE;
            }

            $query->where('label_id', $label->id);
        }

        if ($this->option('release') !== null) {
            $query->where('release_id', (int) $this->option('release'));
        }

        $force = (bool) $this->option('force');
        $ids = $query->orderBy('id')->pluck('id');
        $made = $skipped = $failed = 0;

        foreach ($ids as $index => $id) {
            // Looked up one by one: a long run must see files as they are now.
            $file = ReleaseFile::query()->withoutGlobalScopes()->whereKey($id)->first();

            if ($file === null) {
                continue;
            }

            if (! $force && ($previews->path($file) !== null || ($previews->isOwnPreview($file) && $file->preview_status === AudioPreviewStatus::Ready && $file->duration !== null))) {
                $skipped++;

                continue;
            }

            $started = microtime(true);
            $position = sprintf('[%d/%d]', $index + 1, $ids->count());

            if ($previews->generate($file, $force)) {
                $made++;
                $this->line(sprintf(
                    '%s <info>ready</info>  %s%s',
                    $position,
                    $file->original_name,
                    $file->preview_path === null ? ' (already an MP3)' : sprintf(' (%.1f s)', microtime(true) - $started),
                ));
            } else {
                $failed++;
                $this->line("{$position} <error>failed</error> {$file->original_name}: ".str_replace("\n", ' ', (string) $file->preview_error));
            }
        }

        $this->info("{$made} made, {$skipped} already there, {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
