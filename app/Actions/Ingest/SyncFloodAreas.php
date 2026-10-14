<?php

namespace App\Actions\Ingest;

use App\Data\FloodAreaData;
use App\Models\FloodArea;
use App\Models\Spot;
use App\Services\EnvironmentAgency\FloodMonitoringClient;

/**
 * Links each spot to the Environment Agency flood areas that cover it. The
 * API matches against each area's outline, so a small radius finds the
 * areas the spot sits in or beside; only areas naming the spot's river are
 * kept, so a warning for a beck that passes nearby is not shown.
 */
class SyncFloodAreas
{
    /**
     * How close an area's outline must come to the spot. Spots sit on the
     * river centreline, which an outline can trace just inside its bank.
     */
    public const SEARCH_RADIUS_KM = 1;

    public function __construct(private FloodMonitoringClient $client)
    {
    }

    /**
     * @return array{spots: int, areas: int}
     */
    public function handle(bool $onlyUnsearched = false): array
    {
        $spots = Spot::query()
            ->published()
            ->when($onlyUnsearched, fn ($query) => $query->whereNull('flood_areas_searched_at'))
            ->with('river')
            ->get();

        $linkedSpots = 0;
        $areaIds = [];

        foreach ($spots as $spot) {
            $areas = $this->client->floodAreasNear($spot->latitude, $spot->longitude, self::SEARCH_RADIUS_KM)
                ->filter(fn (FloodAreaData $area) => collect($area->watercourses())->contains(fn (string $name) => $spot->river->matchesWatercourse($name)))
                ->map(fn (FloodAreaData $area) => FloodArea::query()->updateOrCreate(
                    ['code' => $area->code],
                    ['label' => $area->label, 'description' => $area->description, 'river_or_sea' => $area->riverOrSea],
                ));

            $spot->floodAreas()->sync($areas->pluck('id')->all());
            $spot->update(['flood_areas_searched_at' => now()]);

            if ($areas->isNotEmpty()) {
                $linkedSpots++;
            }

            array_push($areaIds, ...$areas->pluck('id')->all());
        }

        return ['spots' => $linkedSpots, 'areas' => count(array_unique($areaIds))];
    }
}
