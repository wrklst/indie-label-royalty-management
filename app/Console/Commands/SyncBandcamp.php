<?php

namespace App\Console\Commands;

use App\Models\BandcampConnection;
use App\Models\Label;
use App\Services\Bandcamp\BandcampException;
use App\Services\Bandcamp\BandcampSync;
use App\Support\CurrentLabel;
use App\Support\Services;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('royalties:sync-bandcamp
    {--label= : Slug or id of one label. Without it every label with an enabled Bandcamp connection is synced}')]
#[Description('Fetch new Bandcamp sales through the Bandcamp API and import them')]
class SyncBandcamp extends Command
{
    public function handle(BandcampSync $sync, CurrentLabel $currentLabel): int
    {
        $currentLabel->set(null);
        $connections = BandcampConnection::query()->orderBy('label_id');

        if ($this->option('label') !== null) {
            $label = Label::query()->where(ctype_digit((string) $this->option('label')) ? 'id' : 'slug', $this->option('label'))->first();

            if ($label === null) {
                $this->error('Label not found. Pass --label with a slug or id.');

                return self::FAILURE;
            }

            $connections = $connections->where('label_id', $label->id)->get();

            if ($connections->isEmpty()) {
                $this->error("{$label->name} has no Bandcamp connection. Set it up under Settings → Connections → Bandcamp.");

                return self::FAILURE;
            }
        } else {
            $connections = $connections->where('enabled', true)->get();
        }

        if ($connections->isEmpty()) {
            $this->line('No label has an enabled Bandcamp connection.');

            return self::SUCCESS;
        }

        $labels = Label::query()->whereKey($connections->pluck('label_id'))->get()->keyBy('id');
        $failed = 0;

        foreach ($connections as $connection) {
            $label = $labels[$connection->label_id] ?? null;
            $name = $label->name ?? "Label {$connection->label_id}";

            // A label that switched Bandcamp off under Settings → Connections is not synced.
            if ($label !== null && ! $label->usesService(Services::BANDCAMP)) {
                $this->line("{$name}: Bandcamp is switched off.");

                continue;
            }

            try {
                $this->info("{$name}: ".$sync->run($connection)->summary());
            } catch (BandcampException $exception) {
                $failed++;
                $this->error("{$name}: {$exception->getMessage()}");
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
                $this->error("{$name}: the sync stopped unexpectedly: {$exception->getMessage()}");
            } finally {
                $currentLabel->set(null);
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
