<?php

namespace App\Actions\Ingest;

use App\Data\GaugeReadingData;
use App\Enums\Agency;
use App\Models\GaugeReading;
use App\Models\GaugeStation;
use App\Services\EnvironmentAgency\FloodMonitoringClient;
use App\Services\NaturalResourcesWales\RiversAndSeasClient;
use App\Services\Sepa\TimeSeriesClient;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Fetches new level readings for every active gauge, picking up from the
 * last stored reading so repeated runs stay cheap. Environment Agency
 * gauges are read one by one; SEPA gauges share requests to stay within
 * its daily credits, NRW gauges share one list of latest readings, and a
 * SEPA or NRW failure is logged without stopping the rest.
 */
class SyncGaugeReadings
{
    private const BACKFILL_DAYS = 7;

    /**
     * A missed NRW reading or two hardly changes a level, so only a gap
     * longer than this (or a new gauge) spends a history call.
     */
    private const NRW_GAP_MINUTES = 60;

    /**
     * History calls per run, so the level and rainfall ingests together
     * stay under NRW's 10 calls a minute. New gauges fill in over a few runs.
     */
    private const NRW_HISTORY_CALLS_PER_RUN = 3;

    public function __construct(private FloodMonitoringClient $client, private TimeSeriesClient $sepa, private RiversAndSeasClient $nrw)
    {
    }

    /**
     * @return array{stations: int, readings: int}
     */
    public function handle(): array
    {
        $stations = GaugeStation::query()
            ->where('is_active', true)
            ->whereNotNull('measure_id')
            ->get();

        // The batched agencies go first, so the slow one-by-one Environment Agency reads cannot hold them up.
        $inserted = $this->syncSepaStations($stations->where('network', Agency::Sepa))
            + $this->syncNrwStations($stations->where('network', Agency::NaturalResourcesWales));

        foreach ($stations->where('network', Agency::EnvironmentAgency) as $station) {
            // A refused or failed request usually means the API is down or throttling us, so the rest wait for the next run.
            try {
                $inserted += $this->syncStation($station);
            } catch (ConnectionException|RequestException $exception) {
                Log::error("Environment Agency level readings failed: {$exception->getMessage()}");

                break;
            }
        }

        return ['stations' => $stations->count(), 'readings' => $inserted];
    }

    public function syncStation(GaugeStation $station): int
    {
        return $this->store($station, $this->client->readingsSince((string) $station->measure_id, $this->since($station)));
    }

    /**
     * @param  EloquentCollection<int, GaugeStation>  $stations
     */
    private function syncSepaStations(EloquentCollection $stations): int
    {
        if ($stations->isEmpty()) {
            return 0;
        }

        try {
            $readings = $this->sepa->readingsSince($stations->mapWithKeys(fn (GaugeStation $station) => [(string) $station->measure_id => $this->since($station)])->all());
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::error("SEPA level readings failed: {$exception->getMessage()}");

            return 0;
        }

        return $stations->sum(fn (GaugeStation $station) => $this->store($station, $readings[(string) $station->measure_id] ?? collect()));
    }

    /**
     * Each gauge's latest reading comes from NRW's station list; gauges with a
     * gap fill it from their history, which also gives their typical range.
     *
     * @param  EloquentCollection<int, GaugeStation>  $stations
     */
    private function syncNrwStations(EloquentCollection $stations): int
    {
        if ($stations->isEmpty() || ! $this->nrw->isEnabled()) {
            return 0;
        }

        $byParameter = $stations->keyBy(fn (GaugeStation $station) => (string) $station->measure_id);

        try {
            $readings = $this->nrw->readingsSince(
                $byParameter->map(fn (GaugeStation $station) => ['externalId' => $station->external_id, 'since' => $this->since($station)])->all(),
                self::NRW_GAP_MINUTES,
                self::NRW_HISTORY_CALLS_PER_RUN,
                function (string $parameter, Collection $history) use ($byParameter) {
                    $range = RiversAndSeasClient::typicalRange($history);

                    if ($range !== null) {
                        $byParameter[$parameter]->update(['typical_range_low' => $range['low'], 'typical_range_high' => $range['high']]);
                    }
                },
            );
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::error("Natural Resources Wales level readings failed: {$exception->getMessage()}");

            return 0;
        }

        return $stations->sum(fn (GaugeStation $station) => $this->store($station, $readings[(string) $station->measure_id] ?? collect()));
    }

    private function since(GaugeStation $station): Carbon
    {
        $latest = $station->readings()->value('measured_at');

        return $latest !== null
            ? Carbon::parse($latest)->addSecond()
            : now()->subDays(self::BACKFILL_DAYS);
    }

    /**
     * @param  Collection<int, GaugeReadingData>  $readings
     */
    private function store(GaugeStation $station, Collection $readings): int
    {
        $rows = $readings
            ->map(fn (GaugeReadingData $reading) => [
                'gauge_station_id' => $station->id,
                'measured_at' => $reading->measuredAt,
                'value' => $reading->value,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($rows === []) {
            return 0;
        }

        GaugeReading::query()->upsert($rows, ['gauge_station_id', 'measured_at'], ['value', 'updated_at']);

        return count($rows);
    }
}
