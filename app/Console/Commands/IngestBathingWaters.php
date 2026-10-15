<?php

namespace App\Console\Commands;

use App\Actions\Ingest\SyncBathingWaterHistory;
use App\Actions\Ingest\SyncBathingWaters;
use App\Actions\Ingest\SyncLakeGauges;
use App\Models\Region;
use App\Services\Conditions\ChangedRegions;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class IngestBathingWaters extends Command
{
    protected $signature = 'ingest:bathing-waters
        {--history : Refetch sample and classification history for every site, not just sites never fetched}
        {--quick : Skip the slow history backfill, for development; a later run fetches it}';

    protected $description = 'Fetch bathing water classifications, samples and pollution forecasts from the Environment Agency';

    public function handle(SyncBathingWaters $sync, SyncBathingWaterHistory $history, SyncLakeGauges $lakeGauges, ChangedRegions $changed): int
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

        // The register sits behind the EA's firewall, which occasionally refuses a request; log it once and carry on.
        try {
            $result = $sync->handle();
            $this->info(sprintf('%d bathing waters updated, %d new samples.', $result['sites'], $result['samples']));
        } catch (ConnectionException|RequestException $exception) {
            $failed = true;
            $reason = $exception instanceof RequestException ? "HTTP {$exception->response->status()}" : $exception->getMessage();
            $this->error("Bathing water register request failed, {$reason}");
            Log::error("Bathing water register request failed: {$reason}");
        }

        if ($this->option('quick')) {
            $this->line('Skipped the history backfill (--quick).');
            $backfill = ['sites' => 0, 'samples' => 0, 'failed' => []];
        } else {
            $backfill = $history->handle(onlyNeverFetched: ! $this->option('history'));
        }

        if ($backfill['sites'] > 0 || $backfill['failed'] !== []) {
            $this->line(sprintf('History fetched for %d bathing waters, %d samples.', $backfill['sites'], $backfill['samples']));
        }

        foreach ($backfill['failed'] as $name) {
            $this->error("{$name}: history fetch failed.");
        }

        // Only regions with beaches or lakes are affected.
        $changed->mark(Region::query()->where(fn ($query) => $query->whereHas('lakes')->orWhereHas('bathingWaters'))->pluck('id'));

        return $failed || $backfill['failed'] !== [] ? self::FAILURE : self::SUCCESS;
    }
}
