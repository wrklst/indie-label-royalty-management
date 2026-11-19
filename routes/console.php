<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('royalties:sync-bandcamp')
    ->weeklyOn(1, '05:00')
    ->withoutOverlapping(180)
    ->onOneServer();

Schedule::command('royalties:book-recurring-expenses')->dailyAt('04:00')->withoutOverlapping(60)->onOneServer();

// Ten tracks a day keep a catalog of a few hundred tracks researched about once a month.
Schedule::command('royalties:research-airplay')->dailyAt('06:30')->withoutOverlapping(60)->onOneServer();

Schedule::command('horizon:snapshot')->everyFiveMinutes();
