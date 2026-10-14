<?php

namespace App\Actions\Rivers;

use App\Models\RainfallStation;
use App\Models\Spot;

/**
 * Links every spot to the nearest active rain gauge within range. Rainfall
 * is context for the spot's surroundings, not a catchment total, so the
 * straight-line nearest gauge is the honest choice.
 */
class LinkNearestRainfallStations
{
    /**
     * Spots with no rain gauge this close get no rainfall figure at all,
     * rather than one from a gauge in a different valley.
     */
    public const MAX_DISTANCE_METRES = 20000;

    /**
     * @return array{spots: int}
     */
    public function handle(): array
    {
        $stations = RainfallStation::query()->where('is_active', true)->whereNotNull('measure_id')->get();
        $linked = 0;

        Spot::query()->each(function (Spot $spot) use ($stations, &$linked) {
            $nearest = $stations
                ->map(fn (RainfallStation $station) => ['station' => $station, 'distance' => $station->distanceMetresFrom($spot->latitude, $spot->longitude)])
                ->filter(fn (array $candidate) => $candidate['distance'] <= self::MAX_DISTANCE_METRES)
                ->sortBy('distance')
                ->first();

            $spot->update(['rainfall_station_id' => $nearest['station']->id ?? null]);

            if ($nearest !== null) {
                $linked++;
            }
        });

        return ['spots' => $linked];
    }
}
