<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

/**
 * Imports the overflow annual returns after a deploy when none are stored or the matching rules changed
 * (ImportOverflowAnnualReturns::MATCHING_VERSION): about 80 requests to the Rivers Trust, a few minutes. A year that
 * fails to read keeps its old rows; the monthly run retries.
 */
class ImportAnnualReturnsIfOutdated implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function handle(): void
    {
        Artisan::call('ingest:overflow-annual-returns', ['--if-outdated' => true]);
    }
}
