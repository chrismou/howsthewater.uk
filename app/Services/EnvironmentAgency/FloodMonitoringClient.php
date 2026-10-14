<?php

namespace App\Services\EnvironmentAgency;

use App\Data\FloodAreaData;
use App\Data\FloodWarningData;
use App\Data\GaugeReadingData;
use App\Data\GaugeStationData;
use App\Data\RainfallStationData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Reads river level and rain gauges, their readings, and flood alerts and
 * warnings from the Environment Agency's real-time flood monitoring API
 * (open data, no key required).
 */
class FloodMonitoringClient
{
    /**
     * @return Collection<int, GaugeStationData>
     */
    public function stationsOnRiver(string $riverName): Collection
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->request()
            ->get($this->url('id/stations'), [
                'riverName' => $riverName,
                'parameter' => 'level',
                '_view' => 'full',
                '_limit' => 500,
            ])
            ->throw()
            ->json('items', []);

        return collect($items)
            ->map(fn (array $item) => GaugeStationData::fromEnvironmentAgency($item))
            ->filter(fn (GaugeStationData $station) => $station->measureId !== null)
            ->values();
    }

    /**
     * One station by its reference, or null when the EA no longer lists it.
     */
    public function station(string $reference): ?GaugeStationData
    {
        $response = $this->request()->get($this->url('id/stations/'.rawurlencode($reference)));

        if ($response->notFound()) {
            return null;
        }

        /** @var array<string, mixed> $item */
        $item = $response->throw()->json('items', []);

        return isset($item['lat'], $item['long']) ? GaugeStationData::fromEnvironmentAgency($item) : null;
    }

    /**
     * Rain gauges within a radius of a point.
     *
     * @return Collection<int, RainfallStationData>
     */
    public function rainfallStationsNear(float $latitude, float $longitude, int $radiusKm): Collection
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->request()
            ->get($this->url('id/stations'), [
                'parameter' => 'rainfall',
                'lat' => $latitude,
                'long' => $longitude,
                'dist' => $radiusKm,
                '_view' => 'full',
                '_limit' => 1000,
            ])
            ->throw()
            ->json('items', []);

        return collect($items)
            ->map(fn (array $item) => RainfallStationData::fromEnvironmentAgency($item))
            ->values();
    }

    /**
     * @return Collection<int, GaugeReadingData>
     */
    public function readingsSince(string $measureId, Carbon $since, int $limit = 2000): Collection
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->request()
            ->get($this->url("id/measures/{$measureId}/readings"), [
                'since' => $since->utc()->format('Y-m-d\TH:i:s\Z'),
                '_sorted' => '',
                '_limit' => $limit,
            ])
            ->throw()
            ->json('items', []);

        return collect($items)
            ->filter(fn (array $item) => isset($item['dateTime'], $item['value']))
            ->map(fn (array $item) => GaugeReadingData::fromEnvironmentAgency($item))
            ->values();
    }

    /**
     * Flood alert and warning areas whose outline comes within a radius of a point.
     *
     * @return Collection<int, FloodAreaData>
     */
    public function floodAreasNear(float $latitude, float $longitude, float $radiusKm): Collection
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->request()
            ->get($this->url('id/floodAreas'), [
                'lat' => $latitude,
                'long' => $longitude,
                'dist' => $radiusKm,
                '_limit' => 500,
            ])
            ->throw()
            ->json('items', []);

        return collect($items)
            ->map(fn (array $item) => FloodAreaData::fromEnvironmentAgency($item))
            ->values();
    }

    /**
     * Every flood alert and warning in England issued in the last 24 hours,
     * including those since lifted. Usually a few dozen items at most.
     *
     * @return Collection<int, FloodWarningData>
     */
    public function floodWarnings(): Collection
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->request()
            ->get($this->url('id/floods'))
            ->throw()
            ->json('items', []);

        return collect($items)
            ->map(fn (array $item) => FloodWarningData::fromEnvironmentAgency($item))
            ->values();
    }

    private function url(string $path): string
    {
        return rtrim(config('services.environment_agency.flood_monitoring_url'), '/').'/'.ltrim($path, '/');
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(60)
            ->retry(2, 2000)
            ->withUserAgent(config('services.user_agent'));
    }
}
