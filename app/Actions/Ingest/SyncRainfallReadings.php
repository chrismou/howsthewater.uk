<?php

namespace App\Actions\Ingest;

use App\Models\RainfallReading;
use App\Models\RainfallStation;
use App\Services\EnvironmentAgency\FloodMonitoringClient;
use Illuminate\Support\Carbon;

/**
 * Fetches new 15 minute rainfall totals for every rain gauge a spot uses,
 * picking up from the last stored reading so repeated runs stay cheap.
 * A gauge that has never returned a reading is retired so spots can be
 * relinked to one that does.
 */
class SyncRainfallReadings
{
    private const BACKFILL_DAYS = 7;

    public function __construct(private FloodMonitoringClient $client) {}

    /**
     * @return array{stations: int, readings: int, retired: int}
     */
    public function handle(): array
    {
        $stations = RainfallStation::query()
            ->where('is_active', true)
            ->whereNotNull('measure_id')
            ->whereHas('spots')
            ->get();

        $inserted = 0;
        $retired = 0;

        foreach ($stations as $station) {
            $hadReadings = $station->readings()->exists();
            $count = $this->syncStation($station);
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
        $latest = $station->readings()->value('measured_at');

        $since = $latest !== null
            ? Carbon::parse($latest)->addSecond()
            : now()->subDays(self::BACKFILL_DAYS);

        $rows = $this->client->readingsSince($station->measure_id, $since)
            ->map(fn ($reading) => [
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
