<?php

namespace App\Console\Commands;

use App\Actions\Ingest\SyncGaugeReadings;
use App\Actions\Ingest\SyncGaugeStations;
use App\Models\River;
use Illuminate\Console\Command;

class IngestGauges extends Command
{
    protected $signature = 'ingest:gauges {--stations : Also refresh the list of gauge stations for each river}';

    protected $description = 'Fetch new river level readings from the Environment Agency';

    public function handle(SyncGaugeStations $syncStations, SyncGaugeReadings $syncReadings): int
    {
        if ($this->option('stations')) {
            River::query()->each(function (River $river) use ($syncStations) {
                $result = $syncStations->handle($river);
                $this->line(sprintf('%s: %d gauge stations.', $river->name, $result['stations']));
            });
        }

        $result = $syncReadings->handle();

        $this->info(sprintf('Stored %d readings across %d stations.', $result['readings'], $result['stations']));

        return self::SUCCESS;
    }
}
