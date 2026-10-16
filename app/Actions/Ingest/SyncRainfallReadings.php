<?php

namespace App\Actions\Ingest;

use App\Data\GaugeReadingData;
use App\Enums\GaugeNetwork;
use App\Models\RainfallReading;
use App\Models\RainfallStation;
use App\Services\EnvironmentAgency\FloodMonitoringClient;
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
 * relinked to one that does. SEPA gauges share requests to stay within
 * its daily credits; when SEPA fails, none of its gauges are retired.
 */
class SyncRainfallReadings
{
    private const BACKFILL_DAYS = 7;

    public function __construct(private FloodMonitoringClient $client, private TimeSeriesClient $sepa)
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

        $sepaReadings = $this->sepaReadings($stations->where('network', GaugeNetwork::Sepa));

        foreach ($stations as $station) {
            if ($station->network === GaugeNetwork::Sepa && $sepaReadings === null) {
                continue;
            }

            $hadReadings = $station->readings()->exists();
            $count = $station->network === GaugeNetwork::Sepa
                ? $this->store($station, $sepaReadings[(string) $station->measure_id] ?? collect())
                : $this->syncStation($station);
            $inserted += $count;

            if (! $hadReadings && $count === 0) {
                $station->update(['is_active' => false]);
                $retired++;
            }
        }

        return ['stations' => $stations->count(), 'readings' => $inserted, 'retired' => $retired];
    }

    public function syncStation(RainfallStation $station): int
    {
        return $this->store($station, $this->client->readingsSince((string) $station->measure_id, $this->since($station)));
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
