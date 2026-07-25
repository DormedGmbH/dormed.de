<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cron-based queue worker (Coolify runs one crontab entry:
// `* * * * * php artisan schedule:run` -> this decides what actually runs).
// Deliberately not a persistent `queue:work` daemon, to stay consistent with
// "no long-running process" (Core Rule 5). --max-time keeps a run from
// bleeding into the next minute's tick; withoutOverlapping is a second,
// independent safety net against two ticks processing the queue at once.
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();
