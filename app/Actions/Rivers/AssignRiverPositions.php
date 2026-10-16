<?php

namespace App\Actions\Rivers;

use App\Models\GaugeStation;
use App\Models\OverflowMonitor;
use App\Models\River;
use App\Models\Spot;
use App\Services\Geo\PolylineProjector;
use App\Services\Geo\WatercourseName;

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
     * Monitors on unnamed tributaries ("Trib Of River Aire") sit away from
     * the main channel, so they get more slack. Their position is where the
     * tributary roughly meets the river.
     */
    private const MAX_TRIBUTARY_OFFSET_METRES = 6000;

    /**
     * How far along the river a metres gauge may be before a closer sea
     * level (mAOD) gauge is linked to the spot instead.
     */
    private const MAX_PREFERRED_GAUGE_DISTANCE_METRES = 10000;

    /**
     * How far along the river a gauge may be at all. Beyond this a spot gets
     * no gauge rather than one reading water tens of kilometres away (the
     * Scottish Tweed has no EA gauges above Kelso).
     */
    private const MAX_GAUGE_DISTANCE_METRES = 25000;

    /**
     * @return array{monitors: int, gauges: int, spots: int}
     */
    public function handle(River $river): array
    {
        if (! $river->hasCentreline()) {
            return ['monitors' => 0, 'gauges' => 0, 'spots' => 0];
        }

        $projector = new PolylineProjector($river->centreline);

        $this->assignJoinPoint($river);

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
                $isUnnamedTributary = WatercourseName::parse($monitor->receiving_watercourse)?->isUnnamedTributary === true;
                $maxOffset = $isUnnamedTributary ? self::MAX_TRIBUTARY_OFFSET_METRES : self::MAX_OFFSET_METRES;

                if ($projection->offsetMetres > $maxOffset) {
                    return;
                }

                $monitor->update(['river_id' => $river->id, 'river_metres' => $projection->distanceAlongMetres]);
                $count++;
            });

        return $count;
    }

    /**
     * Records where a tributary's mouth meets its parent river, or a main river's meets the river it flows into.
     */
    private function assignJoinPoint(River $river): void
    {
        $parent = $river->parent ?? $river->flowsInto;
        $mouth = $river->mouth();

        if ($parent === null || $mouth === null || ! $parent->hasCentreline()) {
            return;
        }

        $projection = (new PolylineProjector($parent->centreline))->project($mouth[0], $mouth[1]);

        $river->update(['joins_at_metres' => $projection->distanceAlongMetres]);
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

                $byDistance = $gauges
                    ->filter(fn (GaugeStation $gauge) => abs($gauge->river_metres - $projection->distanceAlongMetres) <= self::MAX_GAUGE_DISTANCE_METRES)
                    ->sortBy(fn (GaugeStation $gauge) => abs($gauge->river_metres - $projection->distanceAlongMetres));

                // Prefer a gauge reading stage above its own datum, but only if one is reasonably close; a sea level
                // gauge beside the spot says more about the river there than a metres gauge far upstream.
                $nearestGauge = $byDistance
                    ->filter(fn (GaugeStation $gauge) => ! $gauge->isAboveOrdnanceDatum() && abs($gauge->river_metres - $projection->distanceAlongMetres) <= self::MAX_PREFERRED_GAUGE_DISTANCE_METRES)
                    ->first() ?? $byDistance->first();

                $spot->update([
                    'river_metres' => $projection->distanceAlongMetres,
                    'gauge_station_id' => $nearestGauge?->id,
                ]);

                $count++;
            });

        return $count;
    }
}
