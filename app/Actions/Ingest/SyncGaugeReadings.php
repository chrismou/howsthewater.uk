<?php

namespace App\Actions\Ingest;

use App\Models\GaugeReading;
use App\Models\GaugeStation;
use App\Services\EnvironmentAgency\FloodMonitoringClient;
use Illuminate\Support\Carbon;

/**
 * Fetches new level readings for every active gauge, picking up from the
 * last stored reading so repeated runs stay cheap.
 */
class SyncGaugeReadings
{
    private const BACKFILL_DAYS = 7;

    public function __construct(private FloodMonitoringClient $client)
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

        foreach ($stations as $station) {
            $inserted += $this->syncStation($station);
        }

        return ['stations' => $stations->count(), 'readings' => $inserted];
    }

    public function syncStation(GaugeStation $station): int
    {
        $latest = $station->readings()->value('measured_at');

        $since = $latest !== null
            ? Carbon::parse($latest)->addSecond()
            : now()->subDays(self::BACKFILL_DAYS);

        $rows = $this->client->readingsSince($station->measure_id, $since)
            ->map(fn ($reading) => [
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
