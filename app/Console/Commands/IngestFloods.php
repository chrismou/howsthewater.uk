<?php

namespace App\Console\Commands;

use App\Actions\Ingest\RecordFloodWarningHistory;
use App\Actions\Ingest\SyncFloodAreas;
use App\Actions\Ingest\SyncFloodWarnings;
use App\Data\FloodWarningData;
use App\Enums\Agency;
use App\Models\FloodArea;
use App\Services\Conditions\ChangedRegions;
use App\Services\EnvironmentAgency\FloodMonitoringClient;
use App\Services\NaturalResourcesWales\FloodWarningsClient;
use App\Services\Sepa\FloodWarningsClient as SepaFloodWarningsClient;
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

    protected $description = 'Fetch the flood alerts and warnings in force from the Environment Agency, Natural Resources Wales and SEPA';

    public function handle(
        FloodMonitoringClient $client,
        FloodWarningsClient $nrw,
        SepaFloodWarningsClient $sepa,
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

        if ($sepa->isEnabled()) {
            $failed = ! $this->syncScotland($sepa, $syncWarnings, $history) || $failed;
        } elseif (FloodArea::query()->where('agency', Agency::Sepa)->exists()) {
            // Switching SEPA off (the plain rollback if its layers misbehave) also takes its warnings off the pages and closes
            // their history; those events' ended_at then means when we stopped reading, not when SEPA lifted the warning.
            $syncWarnings->handle(collect(), Agency::Sepa);
            $history->handle(collect(), Agency::Sepa);
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
     * SEPA's layers, read and recorded on their own like NRW's. SEPA rewrites every
     * status row with the same refresh time every few minutes while anything has a
     * status, so if that time is hours old, or ahead of our clock, SEPA's side has
     * stopped or its times cannot be trusted: nothing is synced from it (the stored
     * warnings keep the time they were last confirmed, so the pages call them out of
     * date) and the run fails, so the scheduler and the error log both report it. With
     * no status rows at all, the layers' own last edit time is the only signal left,
     * and it shows no more than that SEPA's service is still writing.
     */
    private function syncScotland(SepaFloodWarningsClient $sepa, SyncFloodWarnings $syncWarnings, RecordFloodWarningHistory $history): bool
    {
        try {
            $feed = $sepa->floodWarnings();
            $refreshedAt = $feed->max(fn (FloodWarningData $warning) => $warning->publishedAt) ?? $sepa->lastRefreshedAt();
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            $this->error("SEPA flood warnings failed, {$exception->getMessage()}");
            Log::error("SEPA flood warnings failed: {$exception->getMessage()}");

            return false;
        }

        if ($refreshedAt->lessThan(now()->subHours(SepaFloodWarningsClient::REFRESH_STALE_AFTER_HOURS))) {
            return $this->failScotland("SEPA's flood warning layers were last refreshed {$refreshedAt->diffForHumans()}, so their warnings were not read");
        }

        if ($refreshedAt->greaterThan(now()->addMinutes(SepaFloodWarningsClient::REFRESH_FUTURE_MINUTES))) {
            return $this->failScotland("SEPA's flood warning layers are dated {$refreshedAt->toIso8601String()}, ahead of our clock, so their times cannot be trusted and their warnings were not read");
        }

        $result = $syncWarnings->handle($feed, Agency::Sepa);
        $recorded = $history->handle($feed, Agency::Sepa);

        $this->info(sprintf(
            'SEPA: %d flood alerts and warnings in force for our spots; history: %d opened, %d closed.',
            $result['warnings'],
            $recorded['opened'],
            $recorded['closed'],
        ));

        return true;
    }

    private function failScotland(string $message): bool
    {
        $this->error($message);
        Log::error($message);

        return false;
    }

    /**
     * Links spots never searched (a newly seeded region) to the flood areas around them; --areas re-checks them all.
     * Spots are marked searched one at a time, so a failure part way through resumes on the next run, and a failure
     * (a changed SEPA layer included) still lets the warnings below sync.
     */
    private function linkFloodAreas(SyncFloodAreas $syncAreas): bool
    {
        try {
            $areas = $syncAreas->handle(onlyUnsearched: ! $this->option('areas'));

            if ($areas['spots'] > 0 || $this->option('areas')) {
                $this->line(sprintf('%d spots linked to %d flood areas.', $areas['spots'], $areas['areas']));
            }

            return true;
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            $this->error("Flood area search failed, {$exception->getMessage()}");
            Log::error("Flood area search failed: {$exception->getMessage()}");

            return false;
        }
    }
}
