<?php

namespace App\Actions\Ingest;

use App\Data\GaugeReadingData;
use App\Enums\Agency;
use App\Models\RainfallReading;
use App\Models\RainfallStation;
use App\Services\EnvironmentAgency\FloodMonitoringClient;
use App\Services\NaturalResourcesWales\RiversAndSeasClient;
use App\Services\Sepa\TimeSeriesClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Fetches new 15 minute rainfall totals for every rain gauge a spot, beach or lake uses,
 * picking up from the last stored reading so repeated runs stay cheap.
 * A gauge that has never returned a reading is retired so spots can be
 * relinked to one that does. Environment Agency and NRW gauges share one
 * list of latest readings each, filling gaps a few gauges at a time, and
 * SEPA gauges share requests to stay within its daily credits; when an
 * agency cannot be read, none of its gauges are retired.
 */
class SyncRainfallReadings
{
    private const BACKFILL_DAYS = 7;

    /**
     * Totals are sums of 15 minute readings, so any missed Environment Agency
     * or NRW reading is filled from the gauge's own readings.
     */
    private const GAP_MINUTES = 15;

    /** As in SyncGaugeReadings, so both ingests together stay under NRW's 10 calls a minute. */
    private const NRW_HISTORY_CALLS_PER_RUN = 3;

    /**
     * Many Environment Agency rain gauges report hourly in batches of four,
     * so most catch-ups are routine; this many per run keeps up with them.
     */
    private const ENVIRONMENT_AGENCY_CATCH_UPS_PER_RUN = 50;

    public function __construct(private FloodMonitoringClient $client, private TimeSeriesClient $sepa, private RiversAndSeasClient $nrw)
    {
    }

    /**
     * @return array{stations: int, readings: int, retired: int}
     */
    public function handle(): array
    {
        $stations = RainfallStation::query()
            ->where('is_active', true)
            ->whereNotNull('measure_id')
            ->inUse()
            ->get();

        $inserted = 0;
        $retired = 0;

        $byAgency = [
            Agency::EnvironmentAgency->value => $this->environmentAgencyReadings($stations->where('network', Agency::EnvironmentAgency)),
            Agency::Sepa->value => $this->sepaReadings($stations->where('network', Agency::Sepa)),
            Agency::NaturalResourcesWales->value => $this->nrwReadings($stations->where('network', Agency::NaturalResourcesWales)),
        ];

        foreach ($stations as $station) {
            $readings = $byAgency[$station->network->value] ?? null;

            if ($readings === null) {
                continue;
            }

            $hadReadings = $station->readings()->exists();
            $count = $this->store($station, $readings[(string) $station->measure_id] ?? collect());
            $inserted += $count;

            if (! $hadReadings && $count === 0) {
                $station->update(['is_active' => false]);
                $retired++;
            }
        }

        return ['stations' => $stations->count(), 'readings' => $inserted, 'retired' => $retired];
    }

    /**
     * New readings for the Environment Agency gauges keyed by measure id, or null when the API could not be read.
     *
     * @param  Collection<int, RainfallStation>  $stations
     * @return array<string, Collection<int, GaugeReadingData>>|null
     */
    private function environmentAgencyReadings(Collection $stations): ?array
    {
        if ($stations->isEmpty()) {
            return [];
        }

        try {
            return $this->client->readingsSinceInBulk(
                $stations->mapWithKeys(fn (RainfallStation $station) => [(string) $station->measure_id => $this->since($station)])->all(),
                self::GAP_MINUTES,
                self::ENVIRONMENT_AGENCY_CATCH_UPS_PER_RUN,
            );
        } catch (ConnectionException|RequestException $exception) {
            Log::error("Environment Agency rainfall readings failed: {$exception->getMessage()}");

            return null;
        }
    }

    /**
     * New readings for the SEPA gauges keyed by series id, or null when SEPA could not be read.
     *
     * @param  Collection<int, RainfallStation>  $stations
     * @return array<string, Collection<int, GaugeReadingData>>|null
     */
    private function sepaReadings(Collection $stations): ?array
    {
        if ($stations->isEmpty()) {
            return [];
        }

        try {
            return $this->sepa->readingsSince($stations->mapWithKeys(fn (RainfallStation $station) => [(string) $station->measure_id => $this->since($station)])->all());
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::error("SEPA rainfall readings failed: {$exception->getMessage()}");

            return null;
        }
    }

    /**
     * New readings for the NRW gauges keyed by parameter id, or null when NRW
     * could not be read or its key is not set.
     *
     * @param  Collection<int, RainfallStation>  $stations
     * @return array<string, Collection<int, GaugeReadingData>>|null
     */
    private function nrwReadings(Collection $stations): ?array
    {
        if ($stations->isEmpty()) {
            return [];
        }

        if (! $this->nrw->isEnabled()) {
            return null;
        }

        try {
            return $this->nrw->readingsSince(
                $stations->mapWithKeys(fn (RainfallStation $station) => [(string) $station->measure_id => ['externalId' => $station->external_id, 'since' => $this->since($station)]])->all(),
                self::GAP_MINUTES,
                self::NRW_HISTORY_CALLS_PER_RUN,
            );
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::error("Natural Resources Wales rainfall readings failed: {$exception->getMessage()}");

            return null;
        }
    }

    private function since(RainfallStation $station): Carbon
    {
        $latest = $station->readings()->value('measured_at');

        return $latest !== null
            ? Carbon::parse($latest)->addSecond()
            : now()->subDays(self::BACKFILL_DAYS);
    }

    /**
     * @param  Collection<int, GaugeReadingData>  $readings
     */
    private function store(RainfallStation $station, Collection $readings): int
    {
        $rows = $readings
            ->map(fn (GaugeReadingData $reading) => [
                'rainfall_station_id' => $station->id,
                'measured_at' => $reading->measuredAt,
                'millimetres' => $reading->value,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($rows === []) {
            return 0;
        }

        RainfallReading::query()->upsert($rows, ['rainfall_station_id', 'measured_at'], ['millimetres', 'updated_at']);

        return count($rows);
    }
}
