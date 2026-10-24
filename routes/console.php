<?php

use App\Models\DataSource;
use Illuminate\Support\Facades\Schedule;

// Schedule per TRD §6 / D-54. Times are in Asia/Jakarta.
$tz = config('hunter.schedule_timezone');

Schedule::command('hunter:sync')->dailyAt('01:00')->timezone($tz)->withoutOverlapping()->onOneServer();
Schedule::command('hunter:geocode')->hourly()->timezone($tz)->withoutOverlapping();
Schedule::command('hunter:purge-expired')->dailyAt('02:30')->timezone($tz);
Schedule::command('hunter:score --all')->dailyAt('05:00')->timezone($tz);
Schedule::command('hunter:digest')->dailyAt('06:30')->timezone($tz);
Schedule::call(fn () => DataSource::query()->update(['calls_this_month' => 0]))
    ->name('reset-monthly-calls')->monthlyOn(1, '00:05')->timezone($tz);
