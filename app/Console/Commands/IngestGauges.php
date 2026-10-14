<?php

namespace App\Console\Commands;

use App\Actions\Ingest\SyncGaugeReadings;
use App\Actions\Ingest\SyncGaugeStations;
use App\Actions\Rivers\AssignRiverPositions;
use App\Models\River;
use App\Services\Conditions\ConditionsCache;
use App\Services\Conditions\RegionSummaries;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class IngestGauges extends Command
{
    protected $signature = 'ingest:gauges {--stations : Refresh the gauge stations on every river, not just rivers never searched}';

    protected $description = 'Fetch new river level readings from the Environment Agency';

    public function handle(SyncGaugeStations $syncStations, SyncGaugeReadings $syncReadings, AssignRiverPositions $assign, ConditionsCache $cache, RegionSummaries $summaries): int
    {
        $failed = [];

        // Every run catches up on rivers never searched (a newly seeded region); --stations refreshes them all.
        // A river needs its centreline first, or same-named rivers elsewhere in England would be picked up.
        $rivers = River::query()
            ->unless($this->option('stations'), fn ($query) => $query->whereNotNull('centreline')->whereNull('gauges_discovered_at'))
            ->get();

        foreach ($rivers as $river) {
            // One river's request failing (a timeout, an API blip) must not stop discovery for the rest.
            try {
                $result = $syncStations->handle($river);
                $river->update(['gauges_discovered_at' => now()]);
                $this->line(sprintf('%s: %d gauge stations.', $river->name, $result['stations']));
            } catch (ConnectionException|RequestException $exception) {
                $failed[] = $river->name;
                $this->error("{$river->name}: gauge discovery failed, {$exception->getMessage()}");
            }
        }

        // New gauges need positions before spots can be linked to them.
        if (! $this->option('stations')) {
            $rivers->each(fn (River $river) => $assign->handle($river));
        }

        $result = $syncReadings->handle();
        $cache->flush();
        $summaries->refreshAll();

        $this->info(sprintf('Stored %d readings across %d stations.', $result['readings'], $result['stations']));

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
