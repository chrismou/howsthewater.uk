<?php

namespace App\Actions\Ingest;

use App\Data\GaugeReadingData;
use App\Enums\GaugeNetwork;
use App\Models\GaugeReading;
use App\Models\GaugeStation;
use App\Services\EnvironmentAgency\FloodMonitoringClient;
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
 * its daily credits, and a SEPA failure is logged without stopping the rest.
 */
class SyncGaugeReadings
{
    private const BACKFILL_DAYS = 7;

    public function __construct(private FloodMonitoringClient $client, private TimeSeriesClient $sepa)
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

        $inserted = 0;

        foreach ($stations->where('network', GaugeNetwork::EnvironmentAgency) as $station) {
            $inserted += $this->syncStation($station);
        }

        $inserted += $this->syncSepaStations($stations->where('network', GaugeNetwork::Sepa));

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
