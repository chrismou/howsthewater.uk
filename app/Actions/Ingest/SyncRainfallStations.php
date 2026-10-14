<?php

namespace App\Actions\Ingest;

use App\Actions\Rivers\LinkNearestRainfallStations;
use App\Data\RainfallStationData;
use App\Models\RainfallStation;
use App\Models\Spot;
use App\Services\EnvironmentAgency\FloodMonitoringClient;
use App\Services\Geo\Coordinates;
use Illuminate\Support\Collection;

/**
 * Discovers Environment Agency rain gauges near the spots we cover. One
 * search covers each region's spots, and only gauges close enough to be
 * linked to a spot are stored, so readings are not fetched for the whole
 * country. Searching per region keeps each circle small; one circle around
 * every region would span England and hit the API's result limit.
 */
class SyncRainfallStations
{
    public function __construct(private FloodMonitoringClient $client)
    {
    }

    /**
     * @return array{stations: int}
     */
    public function handle(): array
    {
        $spotsByRegion = Spot::query()->published()->with('river')->get()->groupBy(fn (Spot $spot) => $spot->river->region_id);

        $stations = $spotsByRegion
            ->flatMap(fn (Collection $spots) => $this->client->rainfallStationsNear(...$this->searchCircle($spots))
                ->filter(fn (RainfallStationData $station) => $station->measureId !== null)
                ->filter(fn (RainfallStationData $station) => $this->isNearAnySpot($station, $spots)))
            ->unique(fn (RainfallStationData $station) => $station->externalId);

        foreach ($stations as $data) {
            RainfallStation::query()->updateOrCreate(
                ['external_id' => $data->externalId],
                [
                    'label' => $data->label,
                    'grid_reference' => $data->gridReference,
                    'latitude' => $data->latitude,
                    'longitude' => $data->longitude,
                    'measure_id' => $data->measureId,
                    'is_active' => $data->isActive,
                ],
            );
        }

        return ['stations' => $stations->count()];
    }

    /**
     * The smallest circle around all spots, padded by the linking distance.
     *
     * @param  Collection<int, Spot>  $spots
     * @return array{latitude: float, longitude: float, radiusKm: int}
     */
    private function searchCircle(Collection $spots): array
    {
        $latitude = ($spots->min('latitude') + $spots->max('latitude')) / 2;
        $longitude = ($spots->min('longitude') + $spots->max('longitude')) / 2;

        $reach = $spots->max(fn (Spot $spot) => Coordinates::distanceMetres($latitude, $longitude, $spot->latitude, $spot->longitude));

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radiusKm' => (int) ceil(($reach + LinkNearestRainfallStations::MAX_DISTANCE_METRES) / 1000),
        ];
    }

    /**
     * @param  Collection<int, Spot>  $spots
     */
    private function isNearAnySpot(RainfallStationData $station, Collection $spots): bool
    {
        return $spots->contains(fn (Spot $spot) => Coordinates::distanceMetres($station->latitude, $station->longitude, $spot->latitude, $spot->longitude) <= LinkNearestRainfallStations::MAX_DISTANCE_METRES);
    }
}
