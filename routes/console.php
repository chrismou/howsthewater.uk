<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::command('ingest:overflows')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('ingest:gauges')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('ingest:gauges --stations')->weeklyOn(1, '03:00');
Schedule::command('rivers:positions')->dailyAt('03:30');
Schedule::command('ingest:rainfall')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('ingest:rainfall --stations')->weeklyOn(1, '03:15');
Schedule::command('ingest:floods')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('ingest:floods --areas')->weeklyOn(1, '03:45');
Schedule::command('ingest:bathing-waters')->hourly()->withoutOverlapping();
Schedule::command('ingest:bathing-waters --history')->weeklyOn(1, '04:00');

// Any scheduled run that exits with a failure is logged as an error, which reaches Rollbar in production.
foreach (Schedule::events() as $event) {
    $event->onFailure(fn () => Log::error("Scheduled command failed: {$event->getSummaryForDisplay()}"));
}
