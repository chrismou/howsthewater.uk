<?php

use Illuminate\Support\Facades\Schedule;

// Weekly re-checks sit off the minutes their frequent runs use, since the overlap lock is per command string.
Schedule::command('ingest:overflows')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('ingest:gauges')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('ingest:gauges --stations')->weeklyOn(1, '03:05');
Schedule::command('rivers:positions')->dailyAt('03:30');
Schedule::command('ingest:rainfall')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('ingest:rainfall --stations')->weeklyOn(1, '03:20');
Schedule::command('ingest:floods')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('ingest:floods --areas')->weeklyOn(1, '03:50');
Schedule::command('ingest:bathing-waters')->hourly()->withoutOverlapping();
Schedule::command('ingest:bathing-waters --history')->weeklyOn(1, '04:20');
