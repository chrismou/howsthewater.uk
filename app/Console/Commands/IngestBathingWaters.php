<?php

namespace App\Console\Commands;

use App\Actions\Ingest\SyncBathingWaterHistory;
use App\Actions\Ingest\SyncBathingWaters;
use App\Actions\Ingest\SyncLakeGauges;
use App\Services\Conditions\ConditionsCache;
use App\Services\Conditions\RegionSummaries;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class IngestBathingWaters extends Command
{
    protected $signature = 'ingest:bathing-waters {--history : Refetch sample and classification history for every site, not just sites never fetched}';

    protected $description = 'Fetch bathing water classifications, samples and pollution forecasts from the Environment Agency';

    public function handle(SyncBathingWaters $sync, SyncBathingWaterHistory $history, SyncLakeGauges $lakeGauges, ConditionsCache $cache, RegionSummaries $summaries): int
    {
        $failed = false;

        // Lakes are linked to their level gauge once; the gauge readings sync keeps them current.
        try {
            $lakes = $lakeGauges->handle(onlyUnlinked: ! $this->option('history'));

            if ($lakes['lakes'] > 0) {
                $this->line("{$lakes['lakes']} lakes linked to their level gauge.");
            }
        } catch (ConnectionException|RequestException $exception) {
            $failed = true;
            $this->error("Lake gauge lookup failed, {$exception->getMessage()}");
            Log::error("Lake gauge lookup failed: {$exception->getMessage()}");
        }

        $result = $sync->handle();
        $this->info(sprintf('%d bathing waters updated, %d new samples.', $result['sites'], $result['samples']));

        $backfill = $history->handle(onlyNeverFetched: ! $this->option('history'));

        if ($backfill['sites'] > 0 || $backfill['failed'] !== []) {
            $this->line(sprintf('History fetched for %d bathing waters, %d samples.', $backfill['sites'], $backfill['samples']));
        }

        foreach ($backfill['failed'] as $name) {
            $this->error("{$name}: history fetch failed.");
        }

        $cache->flush();
        $summaries->refreshAll();

        return $failed || $backfill['failed'] !== [] ? self::FAILURE : self::SUCCESS;
    }
}
