<?php

namespace App\Actions\Ingest;

use App\Enums\Agency;
use App\Models\BathingWater;
use App\Services\EnvironmentAgency\BathingWaterClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Backfills each site's recent samples and its yearly classifications.
 * Runs for sites never fetched on every ingest, and for all sites when
 * asked (weekly), since the register only carries the latest of each.
 * Environment Agency sites only: SEPA publishes no history.
 */
class SyncBathingWaterHistory
{
    /**
     * Each site takes two requests, so a first run over every site would
     * otherwise hit the EA in a burst its firewall may refuse.
     */
    private const PAUSE_BETWEEN_SITES_MILLISECONDS = 500;

    public function __construct(private BathingWaterClient $client, private SyncBathingWaters $sync)
    {
    }

    /**
     * @return array{sites: int, samples: int, failed: array<int, string>}
     */
    public function handle(bool $onlyNeverFetched = true): array
    {
        $bathingWaters = BathingWater::query()
            ->where('agency', Agency::EnvironmentAgency)
            ->when($onlyNeverFetched, fn ($query) => $query->whereNull('history_fetched_at'))
            ->get();

        $samples = 0;
        $failed = [];

        foreach ($bathingWaters as $index => $bathingWater) {
            if ($index > 0) {
                Sleep::for(self::PAUSE_BETWEEN_SITES_MILLISECONDS)->milliseconds();
            }

            // One site's request failing must not stop the rest.
            try {
                $samples += $this->sync->storeSamples($bathingWater, $this->client->samples($bathingWater->eubwid));

                foreach ($this->client->classifications($bathingWater->sampling_point) as $year => $classification) {
                    $bathingWater->classifications()->updateOrCreate(['year' => $year], ['classification' => $classification]);
                }

                $bathingWater->update(['history_fetched_at' => now()]);
            } catch (ConnectionException|RequestException $exception) {
                $failed[] = $bathingWater->name;
                Log::error("Bathing water history failed for {$bathingWater->name}: {$exception->getMessage()}");
            }
        }

        return ['sites' => $bathingWaters->count() - count($failed), 'samples' => $samples, 'failed' => $failed];
    }
}
