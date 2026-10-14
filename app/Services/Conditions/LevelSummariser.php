<?php

namespace App\Services\Conditions;

use App\Data\LevelSummary;
use App\Enums\LevelTrend;
use App\Models\GaugeReading;
use App\Models\GaugeStation;
use Illuminate\Support\Collection;

/**
 * Turns a gauge's recent readings into a level, trend and comparison with
 * the station's typical range.
 */
class LevelSummariser
{
    private const TREND_WINDOW_HOURS = 3;

    private const STEADY_THRESHOLD_METRES = 0.03;

    private const CHART_WINDOW_HOURS = 48;

    public function summarise(GaugeStation $station): LevelSummary
    {
        $readings = $station->readings()
            ->where('measured_at', '>=', now()->subHours(self::CHART_WINDOW_HOURS))
            ->get()
            ->sortBy('measured_at')
            ->values();

        /** @var GaugeReading|null $latest */
        $latest = $readings->last();

        if ($latest === null) {
            return new LevelSummary($station, null, null, LevelTrend::Unknown, null, null, collect());
        }

        $change = $this->changeOverWindow($readings, $latest);

        return new LevelSummary(
            station: $station,
            latestValue: $latest->value,
            latestAt: $latest->measured_at,
            trend: $this->trendFor($change, $latest),
            changeLast3Hours: $change,
            relativeToTypical: $this->relativeToTypical($station, $latest->value),
            recentReadings: $readings->map(fn (GaugeReading $reading) => [
                'measured_at' => $reading->measured_at,
                'value' => $reading->value,
            ]),
        );
    }

    /**
     * @param  Collection<int, GaugeReading>  $readings
     */
    private function changeOverWindow(Collection $readings, GaugeReading $latest): ?float
    {
        $windowStart = $latest->measured_at->copy()->subHours(self::TREND_WINDOW_HOURS);

        /** @var GaugeReading|null $earlier */
        $earlier = $readings
            ->filter(fn (GaugeReading $reading) => $reading->measured_at->lessThanOrEqualTo($windowStart))
            ->last();

        return $earlier === null ? null : round($latest->value - $earlier->value, 3);
    }

    private function trendFor(?float $change, GaugeReading $latest): LevelTrend
    {
        if ($latest->measured_at->lessThan(now()->subHours(LevelSummary::STALE_AFTER_HOURS))) {
            return LevelTrend::Unknown;
        }

        if ($change === null || abs($change) < self::STEADY_THRESHOLD_METRES) {
            return LevelTrend::Steady;
        }

        return $change > 0 ? LevelTrend::Rising : LevelTrend::Falling;
    }

    private function relativeToTypical(GaugeStation $station, float $value): ?string
    {
        if ($station->typical_range_low === null || $station->typical_range_high === null) {
            return null;
        }

        if ($value > $station->typical_range_high) {
            return 'above typical range';
        }

        if ($value < $station->typical_range_low) {
            return 'below typical range';
        }

        return 'within typical range';
    }
}
