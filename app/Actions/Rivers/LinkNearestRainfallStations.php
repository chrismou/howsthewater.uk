<?php

namespace App\Actions\Rivers;

use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\RainfallStation;
use App\Models\Spot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Links every spot, beach and lake to the nearest active rain gauge within
 * range. Rainfall is context for the place's surroundings, not a catchment
 * total, so the straight-line nearest gauge is the honest choice.
 */
class LinkNearestRainfallStations
{
    /**
     * Places with no rain gauge this close get no rainfall figure at all,
     * rather than one from a gauge in a different valley.
     */
    public const MAX_DISTANCE_METRES = 20000;

    /**
     * @return array{spots: int, beaches: int, lakes: int}
     */
    public function handle(): array
    {
        $stations = RainfallStation::query()->where('is_active', true)->whereNotNull('measure_id')->get();

        return [
            'spots' => $this->linkEach(Spot::query(), $stations),
            'beaches' => $this->linkEach(BathingWater::query()->beaches(), $stations),
            'lakes' => $this->linkEach(Lake::query(), $stations),
        ];
    }

    /**
     * Links each place the query returns, and counts those within range of a gauge.
     *
     * @template TModel of Spot|BathingWater|Lake
     *
     * @param  Builder<TModel>  $places
     * @param  Collection<int, RainfallStation>  $stations
     */
    private function linkEach(Builder $places, Collection $stations): int
    {
        $linked = 0;

        $places->each(function (Spot|BathingWater|Lake $place) use ($stations, &$linked) {
            $nearest = $stations
                ->map(fn (RainfallStation $station) => ['station' => $station, 'distance' => $station->distanceMetresFrom($place->latitude, $place->longitude)])
                ->filter(fn (array $candidate) => $candidate['distance'] <= self::MAX_DISTANCE_METRES)
                ->sortBy('distance')
                ->first();

            $place->update(['rainfall_station_id' => $nearest['station']->id ?? null]);

            if ($nearest !== null) {
                $linked++;
            }
        });

        return $linked;
    }
}
