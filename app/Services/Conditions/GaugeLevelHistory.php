<?php

namespace App\Services\Conditions;

use App\Models\GaugeReading;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A week of each gauge's readings, thinned to one an hour, for the level charts on a river page. Pages read the
 * river's few gauges at request time rather than carrying a week of points in the cached summaries.
 */
class GaugeLevelHistory
{
    public const DAYS = 7;

    /**
     * Hourly [timestamp, value] points per gauge, oldest first, keyed by gauge id, with the latest reading always
     * kept so the line ends where the level is now.
     *
     * @param  Collection<int, int>  $stationIds
     * @return array<int, list<array{0: int, 1: float}>>
     */
    public function forStations(Collection $stationIds, ?Carbon $now = null): array
    {
        if ($stationIds->isEmpty()) {
            return [];
        }

        $readings = GaugeReading::query()
            ->whereIn('gauge_station_id', $stationIds)
            ->where('measured_at', '>=', ($now ?? now())->copy()->subDays(self::DAYS))
            ->orderBy('measured_at')
            ->toBase()
            ->get(['gauge_station_id', 'measured_at', 'value']);

        $points = [];
        $lastHour = [];

        foreach ($readings as $reading) {
            $station = (int) $reading->gauge_station_id;
            $timestamp = Carbon::parse($reading->measured_at, 'UTC')->getTimestamp();
            $hour = intdiv($timestamp, 3600);

            if (($lastHour[$station] ?? null) === $hour) {
                // Replace the hour's point, so the series ends with the latest reading.
                array_pop($points[$station]);
            }

            $points[$station][] = [$timestamp, (float) $reading->value];
            $lastHour[$station] = $hour;
        }

        return $points;
    }
}
