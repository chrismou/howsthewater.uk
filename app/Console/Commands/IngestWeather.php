<?php

namespace App\Console\Commands;

use App\Actions\Ingest\SyncWeatherForecasts;
use Illuminate\Console\Command;

class IngestWeather extends Command
{
    protected $signature = 'ingest:weather {--force : Read every cell, including those read in the last few hours}';

    protected $description = 'Read the Open-Meteo weather forecast for every grid cell holding a spot, beach or lake';

    public function handle(SyncWeatherForecasts $sync): int
    {
        $result = $sync->handle((bool) $this->option('force'));

        $this->info(sprintf('Read forecasts for %d of %d weather cells.', $result['fetched'], $result['cells']));

        return $result['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
