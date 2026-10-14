<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('ingest:overflows')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('ingest:gauges')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('ingest:gauges --stations')->weeklyOn(1, '03:00');
Schedule::command('rivers:positions')->dailyAt('03:30');
Schedule::command('ingest:rainfall')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('ingest:rainfall --stations')->weeklyOn(1, '03:15');
