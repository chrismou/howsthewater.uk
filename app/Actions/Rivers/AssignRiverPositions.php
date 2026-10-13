<?php

namespace App\Actions\Rivers;

use App\Models\GaugeStation;
use App\Models\OverflowMonitor;
use App\Models\River;
use App\Models\Spot;
use App\Services\Geo\PolylineProjector;

/**
 * Attaches overflow monitors and gauges to a river by watercourse name and
 * computes how far along the centreline every monitor, gauge and spot sits.
 * Spots are then linked to the nearest gauge on the river.
 */
class AssignRiverPositions
{
    /**
     * Monitors further than this from the centreline are ignored, since the
     * watercourse name match was probably a tributary with the same name.
     */
    private const MAX_OFFSET_METRES = 1500;

    /**
     * @return array{monitors: int, gauges: int, spots: int}
     */
    public function handle(River $river): array
    {
        if (! $river->hasCentreline()) {
            return ['monitors' => 0, 'gauges' => 0, 'spots' => 0];
        }

        $projector = new PolylineProjector($river->centreline);

        $monitors = $this->assignMonitors($river, $projector);
        $gauges = $this->assignGauges($river, $projector);
        $spots = $this->assignSpots($river, $projector);

        return ['monitors' => $monitors, 'gauges' => $gauges, 'spots' => $spots];
    }

    private function assignMonitors(River $river, PolylineProjector $projector): int
    {
        $count = 0;

        OverflowMonitor::query()
            ->where(fn ($query) => $query->whereNull('river_id')->orWhere('river_id', $river->id))
            ->each(function (OverflowMonitor $monitor) use ($river, $projector, &$count) {
                if (! $river->matchesWatercourse($monitor->receiving_watercourse)) {
                    return;
                }

                $projection = $projector->project($monitor->latitude, $monitor->longitude);

                if ($projection->offsetMetres > self::MAX_OFFSET_METRES) {
                    return;
                }

                $monitor->update(['river_id' => $river->id, 'river_metres' => $projection->distanceAlongMetres]);
                $count++;
            });

        return $count;
    }

    private function assignGauges(River $river, PolylineProjector $projector): int
    {
        $count = 0;

        GaugeStation::query()
            ->where('river_id', $river->id)
            ->each(function (GaugeStation $station) use ($projector, &$count) {
                $projection = $projector->project($station->latitude, $station->longitude);
                $station->update(['river_metres' => $projection->distanceAlongMetres]);
                $count++;
            });

        return $count;
    }

    private function assignSpots(River $river, PolylineProjector $projector): int
    {
        $gauges = GaugeStation::query()
            ->where('river_id', $river->id)
            ->where('is_active', true)
            ->whereNotNull('river_metres')
            ->get();

        $count = 0;

        Spot::query()
            ->where('river_id', $river->id)
            ->each(function (Spot $spot) use ($projector, $gauges, &$count) {
                $projection = $projector->project($spot->latitude, $spot->longitude);

                $nearestGauge = $gauges
                    ->sortBy(fn (GaugeStation $gauge) => abs($gauge->river_metres - $projection->distanceAlongMetres))
                    ->first();

                $spot->update([
                    'river_metres' => $projection->distanceAlongMetres,
                    'gauge_station_id' => $nearestGauge?->id,
                ]);

                $count++;
            });

        return $count;
    }
}
