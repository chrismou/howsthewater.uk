<?php

namespace App\Console\Commands;

use App\Actions\Ingest\RecordFloodWarningHistory;
use App\Actions\Ingest\SyncFloodAreas;
use App\Actions\Ingest\SyncFloodWarnings;
use App\Enums\Agency;
use App\Services\Conditions\ChangedRegions;
use App\Services\EnvironmentAgency\FloodMonitoringClient;
use App\Services\NaturalResourcesWales\FloodWarningsClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class IngestFloods extends Command
{
    protected $signature = 'ingest:floods
        {--areas : Refresh the flood areas covering every spot, not just spots never searched}
        {--quick : Skip the slow flood area search, for development; a later run picks the spots up}';

    protected $description = 'Fetch the flood alerts and warnings in force from the Environment Agency and Natural Resources Wales';

    public function handle(
        FloodMonitoringClient $client,
        FloodWarningsClient $nrw,
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

        $failed = ! $this->syncEngland($client, $syncWarnings, $history) || $failed;

        if ($nrw->isEnabled()) {
            $failed = ! $this->syncWales($nrw, $syncWarnings, $history) || $failed;
        }

        $changed->markAll();

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * One read of the EA's national feed serves both the warnings in force for our spots and the history of all of
     * them. A failure leaves the stored warnings and open events as they were, and Wales is still read.
     */
    private function syncEngland(FloodMonitoringClient $client, SyncFloodWarnings $syncWarnings, RecordFloodWarningHistory $history): bool
    {
        try {
            $feed = $client->floodWarnings();
        } catch (ConnectionException|RequestException $exception) {
            $this->error("Environment Agency flood warnings failed, {$exception->getMessage()}");
            Log::error("Environment Agency flood warnings failed: {$exception->getMessage()}");

            return false;
        }

        $result = $syncWarnings->handle($feed);
        $recorded = $history->handle($feed);

        $this->info(sprintf(
            'Environment Agency: %d flood alerts and warnings in force for our spots; history: %d opened, %d closed.',
            $result['warnings'],
            $recorded['opened'],
            $recorded['closed'],
        ));

        return true;
    }

    /**
     * NRW's feed, read and recorded on its own so an NRW failure leaves the
     * Environment Agency's warnings and the open Welsh events as they were.
     */
    private function syncWales(FloodWarningsClient $nrw, SyncFloodWarnings $syncWarnings, RecordFloodWarningHistory $history): bool
    {
        try {
            $feed = $nrw->floodWarnings();
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            $this->error("Natural Resources Wales flood warnings failed, {$exception->getMessage()}");
            Log::error("Natural Resources Wales flood warnings failed: {$exception->getMessage()}");

            return false;
        }

        $result = $syncWarnings->handle($feed, Agency::NaturalResourcesWales);
        $recorded = $history->handle($feed, Agency::NaturalResourcesWales);

        $this->info(sprintf(
            'Natural Resources Wales: %d flood alerts and warnings in force for our spots; history: %d opened, %d closed.',
            $result['warnings'],
            $recorded['opened'],
            $recorded['closed'],
        ));

        return true;
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
