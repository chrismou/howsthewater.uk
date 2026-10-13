<?php

namespace App\Console\Commands;

use App\Actions\Ingest\SyncOverflowMonitors;
use Illuminate\Console\Command;

class IngestOverflows extends Command
{
    protected $signature = 'ingest:overflows';

    protected $description = 'Fetch current storm overflow status and record spill events';

    public function handle(SyncOverflowMonitors $sync): int
    {
        $result = $sync->handle();

        $this->info(sprintf(
            'Synced %d monitors (%d events opened, %d closed).',
            $result['monitors'],
            $result['events_opened'],
            $result['events_closed'],
        ));

        return self::SUCCESS;
    }
}
