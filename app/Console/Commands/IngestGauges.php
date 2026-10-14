<?php

namespace App\Console\Commands;

use App\Actions\Ingest\SyncGaugeReadings;
use App\Actions\Ingest\SyncGaugeStations;
use App\Models\River;
use App\Services\Conditions\ConditionsCache;
use App\Services\Conditions\RegionSummaries;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class IngestGauges extends Command
{
    protected $signature = 'ingest:gauges {--stations : Also refresh the list of gauge stations for each river}';

    protected $description = 'Fetch new river level readings from the Environment Agency';

    public function handle(SyncGaugeStations $syncStations, SyncGaugeReadings $syncReadings, ConditionsCache $cache, RegionSummaries $summaries): int
    {
        $failed = [];

        if ($this->option('stations')) {
            // One river's request failing (a timeout, an API blip) must not stop discovery for the rest.
            River::query()->each(function (River $river) use ($syncStations, &$failed) {
                try {
                    $result = $syncStations->handle($river);
                    $this->line(sprintf('%s: %d gauge stations.', $river->name, $result['stations']));
                } catch (ConnectionException|RequestException $exception) {
                    $failed[] = $river->name;
                    $this->error("{$river->name}: gauge discovery failed, {$exception->getMessage()}");
                }
            });
        }

        $result = $syncReadings->handle();
        $cache->flush();
        $summaries->refreshAll();

        $this->info(sprintf('Stored %d readings across %d stations.', $result['readings'], $result['stations']));

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
