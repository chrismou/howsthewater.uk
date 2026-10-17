<?php

use Illuminate\Support\Facades\Schedule;

// Weekly re-checks sit off the minutes their frequent runs use, since the overlap lock is per command string. Locks
// expire after 30 minutes (an hour for the hourly run), so a killed run cannot stop a feed for the default 24 hours.
Schedule::command('ingest:overflows')->everyFifteenMinutes()->withoutOverlapping(30);
Schedule::command('ingest:gauges')->everyFifteenMinutes()->withoutOverlapping(30);
Schedule::command('ingest:gauges --stations')->weeklyOn(1, '03:05');
Schedule::command('rivers:positions')->dailyAt('03:30');
Schedule::command('ingest:rainfall')->everyFifteenMinutes()->withoutOverlapping(30);
Schedule::command('ingest:rainfall --stations')->weeklyOn(1, '03:20');
Schedule::command('ingest:floods')->everyFifteenMinutes()->withoutOverlapping(30);
Schedule::command('ingest:floods --areas')->weeklyOn(1, '03:50');
Schedule::command('ingest:bathing-waters')->hourly()->withoutOverlapping(60);
Schedule::command('ingest:bathing-waters --history')->weeklyOn(1, '04:20');
// Every three hours, off the quarter hours the other ingests use. A run reads about 600 forecasts in paced batches
// and can take ten minutes when Open-Meteo is slow.
Schedule::command('ingest:weather')->cron('7 */3 * * *')->withoutOverlapping(60);
// The annual returns come out once a year (spring), so a monthly read picks up a new year and any corrections.
Schedule::command('ingest:overflow-annual-returns')->monthlyOn(2, '05:10');
// Natural Resources Wales asks that its data is not kept over a year.
Schedule::command('nrw:prune')->dailyAt('04:40');
// Every minute, so its one read per turn keeps an even pace; most runs find it is not yet due and exit.
Schedule::command('ingest:sepa-bathing-samples')->everyMinute()->withoutOverlapping(5);

// Last, so it runs after the quarter hour's ingests in the same scheduler pass (due tasks run one after another, in
// the order registered here): one rebuild of whatever they marked changed, instead of one per ingest.
Schedule::command('regions:refresh')->everyFifteenMinutes()->withoutOverlapping(30);
