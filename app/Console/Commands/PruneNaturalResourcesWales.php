<?php

namespace App\Console\Commands;

use App\Actions\Ingest\PruneNaturalResourcesWalesData;
use Illuminate\Console\Command;

class PruneNaturalResourcesWales extends Command
{
    protected $signature = 'nrw:prune';

    protected $description = 'Delete Natural Resources Wales readings and flood warning history over a year old, as NRW asks';

    public function handle(PruneNaturalResourcesWalesData $prune): int
    {
        $result = $prune->handle();

        $this->info(sprintf('Deleted %d level readings, %d rainfall readings and %d flood warning events from NRW.', $result['levels'], $result['rainfall'], $result['flood_warnings']));

        return self::SUCCESS;
    }
}
