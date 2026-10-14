<?php

namespace App\Console\Commands;

use App\Actions\Ingest\SyncOverflowMonitors;
use App\Services\Conditions\ConditionsCache;
use App\Services\Conditions\RegionSummaries;
use Illuminate\Console\Command;

class IngestOverflows extends Command
{
    protected $signature = 'ingest:overflows';

    protected $description = 'Fetch current storm overflow status and record spill events';

    public function handle(SyncOverflowMonitors $sync, ConditionsCache $cache, RegionSummaries $summaries): int
    {
        $result = $sync->handle();
        $cache->flush();
        $summaries->refreshAll();

        $this->info(sprintf(
            'Synced %d monitors (%d events opened, %d closed).',
            $result['monitors'],
            $result['events_opened'],
            $result['events_closed'],
        ));

        return self::SUCCESS;
    }
}
