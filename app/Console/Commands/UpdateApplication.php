<?php

namespace App\Console\Commands;

use App\Support\Updater;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('royalties:update
    {--check : Only say whether a newer version exists}
    {--scheduled : Run only when an update was asked for in the interface, or automatic updates are due}
    {--without-backup : Do not back up the database first}')]
#[Description('Update this installation to the newest tagged version of its repository')]
class UpdateApplication extends Command
{
    public function handle(): int
    {
        $settings = Updater::settings();
        $requested = $settings['requested'];
        // Left alone once a day at the chosen hour; a version that failed is not tried again by itself.
        $automatic = $this->option('scheduled') && ! $requested;

        if ($this->option('scheduled')) {
            $due = $settings['auto'] && now()->hour === $settings['hour'] && $settings['checked_on'] !== now()->toDateString();

            if (! $requested && ! $due) {
                return self::SUCCESS;
            }

            Updater::remember(['requested' => false, 'checked_on' => now()->toDateString()]);
        }

        try {
            $obstacle = Updater::obstacle();

            if ($obstacle !== null) {
                throw new RuntimeException($obstacle);
            }

            $latest = Updater::latest();

            if ($latest === null || ! Updater::isBehind($latest)) {
                $this->line($latest === null ? 'The repository has no tagged version yet.' : 'This installation already runs '.Updater::current().'; the newest version is '.$latest.'.');

                return self::SUCCESS;
            }

            if ($this->option('check')) {
                $this->line("Version {$latest} is available. This installation runs ".Updater::current().'.');

                return self::SUCCESS;
            }

            if ($automatic && $settings['last'] !== null && $settings['last']['tag'] === $latest && $settings['last']['status'] !== 'ok') {
                $this->line("Version {$latest} failed before and is not tried again automatically.");

                return self::SUCCESS;
            }

            $this->line("Updating to {$latest}. The site is in maintenance mode meanwhile.");

            if (Updater::update($latest, ! $this->option('without-backup'))) {
                $this->info("This installation now runs {$latest}.");

                return self::SUCCESS;
            }
        } catch (RuntimeException $exception) {
            if ($this->option('scheduled')) {
                Updater::remember(['last' => [
                    'tag' => $latest ?? '', 'from' => Updater::current(), 'status' => 'refused',
                    'message' => $exception->getMessage(), 'at' => now()->toIso8601String(), 'backup' => null,
                ]]);
            }

            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->error(Updater::settings()['last']['status'] === 'broken'
            ? 'The update failed and going back failed as well. The site is in maintenance mode. See storage/logs/update.log.'
            : 'The update failed. The previous version runs again. See storage/logs/update.log.');

        return self::FAILURE;
    }
}
