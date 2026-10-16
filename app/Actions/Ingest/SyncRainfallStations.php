<?php

namespace App\Actions\Ingest;

use App\Actions\Rivers\LinkNearestRainfallStations;
use App\Data\RainfallStationData;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\RainfallStation;
use App\Models\Spot;
use App\Services\EnvironmentAgency\FloodMonitoringClient;
use App\Services\Geo\Coordinates;
use App\Services\Sepa\TimeSeriesClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Discovers Environment Agency and SEPA rain gauges near the places we show
 * rainfall for: spots, beaches and lakes. One search covers each region's places,
 * and only gauges close enough to be linked to one are stored, so readings
 * are not fetched for the whole country. Searching per region keeps each
 * circle small; one circle around every region would span England and hit
 * the API's result limit.
 */
class SyncRainfallStations
{
    public function __construct(private FloodMonitoringClient $client, private TimeSeriesClient $sepa)
    {
    }

    /**
     * @return array{stations: int}
     */
    public function handle(): array
    {
        $places = $this->placesByRegion();

        $stations = $places
            ->flatMap(fn (Collection $points) => $this->client->rainfallStationsNear(...$this->searchCircle($points))
                ->filter(fn (RainfallStationData $station) => $station->measureId !== null)
                ->filter(fn (RainfallStationData $station) => $this->isNearAnyPlace($station, $points)))
            ->concat($this->sepaStationsNear($places->flatten(1)))
            ->unique(fn (RainfallStationData $station) => $station->externalId);

        foreach ($stations as $data) {
            RainfallStation::query()->updateOrCreate(
                ['external_id' => $data->externalId],
                [
                    'network' => $data->network,
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
     * SEPA lists every Scottish rain gauge in one request, so its gauges are
     * filtered against every place at once. A failure is logged and the
     * Environment Agency gauges are still stored.
     *
     * @param  Collection<int, array{0: float, 1: float}>  $points
     * @return Collection<int, RainfallStationData>
     */
    private function sepaStationsNear(Collection $points): Collection
    {
        if ($points->isEmpty()) {
            return collect();
        }

        try {
            return $this->sepa->rainfallStations()
                ->filter(fn (RainfallStationData $station) => $this->isNearAnyPlace($station, $points))
                ->values();
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::error("SEPA rain gauge discovery failed: {$exception->getMessage()}");

            return collect();
        }
    }

    /**
     * Every spot, beach and lake as [lat, lng], grouped by region.
     *
     * @return Collection<int, Collection<int, array{0: float, 1: float}>>
     */
    private function placesByRegion(): Collection
    {
        $spots = Spot::query()->published()->with('river')->get()
            ->map(fn (Spot $spot) => ['region' => $spot->river->region_id, 'point' => [$spot->latitude, $spot->longitude]]);
        $beaches = BathingWater::query()->beaches()->whereNotNull('region_id')->get()
            ->map(fn (BathingWater $beach) => ['region' => $beach->region_id, 'point' => [$beach->latitude, $beach->longitude]]);
        $lakes = Lake::query()->get()
            ->map(fn (Lake $lake) => ['region' => $lake->region_id, 'point' => [$lake->latitude, $lake->longitude]]);

        return $spots->concat($beaches)->concat($lakes)
            ->groupBy('region')
            ->map(fn (Collection $places) => $places->map(fn (array $place): array => $place['point'])->values())
            ->values();
    }

    /**
     * The smallest circle around all the points, padded by the linking distance.
     *
     * @param  Collection<int, array{0: float, 1: float}>  $points
     * @return array{latitude: float, longitude: float, radiusKm: int}
     */
    private function searchCircle(Collection $points): array
    {
        $latitudes = $points->map(fn (array $point) => $point[0])->all();
        $longitudes = $points->map(fn (array $point) => $point[1])->all();
        $latitude = (min($latitudes) + max($latitudes)) / 2;
        $longitude = (min($longitudes) + max($longitudes)) / 2;

        $reach = (float) $points->map(fn (array $point) => Coordinates::distanceMetres($latitude, $longitude, $point[0], $point[1]))->max();

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radiusKm' => (int) ceil(($reach + LinkNearestRainfallStations::MAX_DISTANCE_METRES) / 1000),
        ];
    }

    /**
     * @param  Collection<int, array{0: float, 1: float}>  $points
     */
    private function isNearAnyPlace(RainfallStationData $station, Collection $points): bool
    {
        return $points->contains(fn (array $point) => Coordinates::distanceMetres($station->latitude, $station->longitude, $point[0], $point[1]) <= LinkNearestRainfallStations::MAX_DISTANCE_METRES);
    }
}
