<?php

namespace App\Console\Commands;

use App\Actions\Ingest\SyncRainfallReadings;
use App\Actions\Ingest\SyncRainfallStations;
use App\Actions\Rivers\LinkNearestRainfallStations;
use App\Services\Conditions\ConditionsCache;
use App\Services\Conditions\RiverOverviewService;
use Illuminate\Console\Command;

class IngestRainfall extends Command
{
    /**
     * Each pass may retire silent gauges and relink spots to live ones, so
     * readings are fetched again for the new links. Bounded, so a region
     * full of dead gauges cannot loop forever.
     */
    private const MAX_RELINK_PASSES = 3;

    protected $signature = 'ingest:rainfall {--stations : Also refresh the rain gauges near each spot and relink spots to the nearest}';

    protected $description = 'Fetch new rainfall readings from the Environment Agency';

    public function handle(
        SyncRainfallStations $syncStations,
        LinkNearestRainfallStations $link,
        SyncRainfallReadings $syncReadings,
        ConditionsCache $cache,
        RiverOverviewService $overview,
    ): int {
        if ($this->option('stations')) {
            $stations = $syncStations->handle();
            $linked = $link->handle();
            $this->line(sprintf('%d rain gauges near spots, %d spots linked to one.', $stations['stations'], $linked['spots']));
        }

        $readings = 0;

        for ($pass = 0; $pass < self::MAX_RELINK_PASSES; $pass++) {
            $result = $syncReadings->handle();
            $readings += $result['readings'];

            if ($result['retired'] === 0) {
                break;
            }

            $linked = $link->handle();
            $this->line(sprintf('%d rain gauges never reported and were retired; %d spots now linked to a live one.', $result['retired'], $linked['spots']));
        }

        $cache->flush();
        $overview->all();

        $this->info(sprintf('Stored %d rainfall readings across %d stations.', $readings, $result['stations']));

        return self::SUCCESS;
    }
}
