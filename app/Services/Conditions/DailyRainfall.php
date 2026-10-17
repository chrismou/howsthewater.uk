<?php

namespace App\Services\Conditions;

use App\Models\RainfallReading;
use App\Models\RainfallStation;
use Illuminate\Support\Carbon;

/**
 * A rain gauge's total per UK day over the spill history window, for charts that set spills beside rainfall.
 */
class DailyRainfall
{
    /**
     * Rain per UK day, oldest first, with every day present: null for a day the gauge sent no readings, so a silent
     * gauge never reads as a dry day.
     *
     * @return array<string, float|null>
     */
    public function forStation(RainfallStation $station, Carbon $now): array
    {
        $timezone = (string) config('app.display_timezone', 'Europe/London');
        $firstDay = $now->copy()->timezone($timezone)->startOfDay()->subDays(SpillHistoryService::HISTORY_DAYS - 1);
        $totals = [];

        foreach (range(0, SpillHistoryService::HISTORY_DAYS - 1) as $offset) {
            $totals[$firstDay->copy()->addDays($offset)->toDateString()] = null;
        }

        $readings = RainfallReading::query()
            ->where('rainfall_station_id', $station->id)
            ->where('measured_at', '>', $firstDay->copy()->utc())
            ->toBase()
            ->get(['measured_at', 'millimetres']);

        foreach ($readings as $reading) {
            // A reading closes its 15 minutes, so one at midnight belongs to the day before.
            $day = Carbon::parse($reading->measured_at, 'UTC')->subSecond()->timezone($timezone)->toDateString();

            if (array_key_exists($day, $totals)) {
                $totals[$day] = ($totals[$day] ?? 0.0) + (float) $reading->millimetres;
            }
        }

        return array_map(fn (?float $millimetres) => $millimetres === null ? null : round($millimetres, 1), $totals);
    }
}
