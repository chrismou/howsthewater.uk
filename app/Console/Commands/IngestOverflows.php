<?php

namespace App\Console\Commands;

use App\Actions\Ingest\SyncOverflowMonitors;
use App\Actions\Rivers\AssignRiverPositions;
use App\Models\River;
use App\Services\Conditions\ChangedRegions;
use Illuminate\Console\Command;

class IngestOverflows extends Command
{
    protected $signature = 'ingest:overflows';

    protected $description = 'Fetch current storm overflow status and record spill events';

    public function handle(SyncOverflowMonitors $sync, AssignRiverPositions $assign, ChangedRegions $changed): int
    {
        $result = $sync->handle();

        // A new region's monitors first appear here, after the deploy has already positioned everything, so
        // place them straight away rather than leaving their spots blank until the nightly positioning run.
        if ($result['monitors_added'] > 0) {
            River::query()->each(fn (River $river) => $assign->handle($river));
            $this->line("{$result['monitors_added']} new monitors positioned.");
        }

        $changed->markAll();

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
