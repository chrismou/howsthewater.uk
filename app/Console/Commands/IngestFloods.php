<?php

namespace App\Console\Commands;

use App\Actions\Ingest\SyncFloodAreas;
use App\Actions\Ingest\SyncFloodWarnings;
use App\Services\Conditions\ConditionsCache;
use App\Services\Conditions\RegionSummaries;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class IngestFloods extends Command
{
    protected $signature = 'ingest:floods {--areas : Refresh the flood areas covering every spot, not just spots never searched}';

    protected $description = 'Fetch the flood alerts and warnings in force from the Environment Agency';

    public function handle(SyncFloodAreas $syncAreas, SyncFloodWarnings $syncWarnings, ConditionsCache $cache, RegionSummaries $summaries): int
    {
        $failed = false;

        // Every run links spots never searched (a newly seeded region); --areas re-checks them all.
        // Spots are marked searched one at a time, so a failure part way through resumes on the next run.
        try {
            $areas = $syncAreas->handle(onlyUnsearched: ! $this->option('areas'));

            if ($areas['spots'] > 0 || $this->option('areas')) {
                $this->line(sprintf('%d spots linked to %d flood areas.', $areas['spots'], $areas['areas']));
            }
        } catch (ConnectionException|RequestException $exception) {
            $failed = true;
            $this->error("Flood area search failed, {$exception->getMessage()}");
        }

        $result = $syncWarnings->handle();
        $cache->flush();
        $summaries->refreshAll();

        $this->info(sprintf('%d flood alerts and warnings in force for our spots.', $result['warnings']));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
