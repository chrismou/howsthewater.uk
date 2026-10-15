<?php

namespace App\Services\Conditions;

use App\Data\RainfallSummary;
use App\Models\RainfallReading;
use App\Models\RainfallStation;
use App\Models\Spot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Totals a rain gauge's recent 15 minute readings into the windows shown
 * for a spot, beach or lake.
 */
class RainfallSummariser
{
    private const WINDOW_HOURS = 72;

    public function summarise(Spot $spot, RainfallStation $station): RainfallSummary
    {
        return $this->summariseAt($spot->latitude, $spot->longitude, $station);
    }

    /**
     * Totals for the gauge, with its distance from the given place.
     */
    public function summariseAt(float $latitude, float $longitude, RainfallStation $station): RainfallSummary
    {
        $readings = $station->readings()
            ->where('measured_at', '>=', now()->subHours(self::WINDOW_HOURS))
            ->get();

        /** @var RainfallReading|null $latest */
        $latest = $readings->first();

        return new RainfallSummary(
            station: $station,
            distanceMetres: $station->distanceMetresFrom($latitude, $longitude),
            latestAt: $latest?->measured_at,
            last6HoursMillimetres: $this->totalSince($readings, now()->subHours(6)),
            last24HoursMillimetres: $this->totalSince($readings, now()->subHours(24)),
            last72HoursMillimetres: $this->totalSince($readings, now()->subHours(self::WINDOW_HOURS)),
        );
    }

    /**
     * @param  Collection<int, RainfallReading>  $readings
     */
    private function totalSince(Collection $readings, Carbon $since): float
    {
        return round($readings
            ->filter(fn (RainfallReading $reading) => $reading->measured_at->greaterThan($since))
            ->sum('millimetres'), 1);
    }
}
