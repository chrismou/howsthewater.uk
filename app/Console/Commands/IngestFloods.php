<?php

namespace App\Console\Commands;

use App\Actions\Ingest\RecordFloodWarningHistory;
use App\Actions\Ingest\SyncFloodAreas;
use App\Actions\Ingest\SyncFloodWarnings;
use App\Services\Conditions\ChangedRegions;
use App\Services\EnvironmentAgency\FloodMonitoringClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class IngestFloods extends Command
{
    protected $signature = 'ingest:floods
        {--areas : Refresh the flood areas covering every spot, not just spots never searched}
        {--quick : Skip the slow flood area search, for development; a later run picks the spots up}';

    protected $description = 'Fetch the flood alerts and warnings in force from the Environment Agency';

    public function handle(
        FloodMonitoringClient $client,
        SyncFloodAreas $syncAreas,
        SyncFloodWarnings $syncWarnings,
        RecordFloodWarningHistory $history,
        ChangedRegions $changed,
    ): int {
        $failed = false;

        if ($this->option('quick')) {
            $this->line('Skipped the flood area search (--quick).');
        } else {
            $failed = ! $this->linkFloodAreas($syncAreas);
        }

        // One read of the national feed serves both the warnings in force for our spots and the history of all of them.
        $feed = $client->floodWarnings();
        $result = $syncWarnings->handle($feed);
        $recorded = $history->handle($feed);

        $changed->markAll();

        $this->info(sprintf(
            '%d flood alerts and warnings in force for our spots; history: %d opened, %d closed.',
            $result['warnings'],
            $recorded['opened'],
            $recorded['closed'],
        ));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Links spots never searched (a newly seeded region) to the flood areas around them; --areas re-checks them all.
     * Spots are marked searched one at a time, so a failure part way through resumes on the next run.
     */
    private function linkFloodAreas(SyncFloodAreas $syncAreas): bool
    {
        try {
            $areas = $syncAreas->handle(onlyUnsearched: ! $this->option('areas'));

            if ($areas['spots'] > 0 || $this->option('areas')) {
                $this->line(sprintf('%d spots linked to %d flood areas.', $areas['spots'], $areas['areas']));
            }

            return true;
        } catch (ConnectionException|RequestException $exception) {
            $this->error("Flood area search failed, {$exception->getMessage()}");
            Log::error("Flood area search failed: {$exception->getMessage()}");

            return false;
        }
    }
}
