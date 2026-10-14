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
            'Synced %d monitors from %d %s (%d events opened, %d closed).',
            $result['monitors'],
            $result['feeds'],
            $result['feeds'] === 1 ? 'feed' : 'feeds',
            $result['events_opened'],
            $result['events_closed'],
        ));

        foreach ($result['failed'] as $name) {
            $this->error("{$name} feed failed; its monitors were left as they were.");
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
