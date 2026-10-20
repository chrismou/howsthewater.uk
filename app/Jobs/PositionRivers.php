<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

/**
 * Imports the centrelines of rivers that lack one (newly seeded, or cleared by a migration to refetch), then positions
 * every river's monitors, gauges and spots, after each deploy. Positioning marks every region changed, so the next
 * regions:refresh shows the new positions. Up to half an hour with every water company recorded.
 */
class PositionRivers implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3300;

    public function handle(): void
    {
        Artisan::call('rivers:centrelines');
        Artisan::call('rivers:positions');
    }
}
