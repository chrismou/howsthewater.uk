<?php

namespace App\Console\Commands;

use App\Jobs\ImportAnnualReturnsIfOutdated;
use App\Jobs\PositionRivers;
use App\Jobs\QueueShareCards;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class DispatchDeployJobs extends Command
{
    protected $signature = 'deploy:dispatch-jobs';

    protected $description = 'Queue the work a deploy leaves to the background: centrelines and positions, then share cards, and the annual returns if outdated';

    public function handle(): int
    {
        // Cards come after positions, as a newly fetched centreline changes its river's card.
        Bus::chain([new PositionRivers(), new QueueShareCards()])->dispatch();
        ImportAnnualReturnsIfOutdated::dispatch();

        $this->info('Queued river positions, share cards and the annual returns check.');

        return self::SUCCESS;
    }
}
