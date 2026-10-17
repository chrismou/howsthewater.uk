<?php

namespace App\Services\NaturalResourcesWales;

use App\Data\FloodAreaData;
use App\Data\FloodWarningData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Reads Natural Resources Wales's live flood alerts and warnings, and the
 * outlines of its alert and warning areas from DataMapWales (open, no key),
 * which the warnings feed refers to by code but does not carry.
 */
class FloodWarningsClient
{
    /** NRW's flood warning areas, and its flood alert areas. */
    private const AREA_LAYERS = ['inspire-nrw:NRW_FLOOD_WARNING', 'inspire-nrw:NRW_FLOOD_WATCH_AREAS'];

    /**
     * The extent of NRW's areas, padded slightly; places outside it are not searched.
     *
     * @var array{south: float, west: float, north: float, east: float}
     */
    private const AREA_EXTENT = ['south' => 51.30, 'west' => -5.35, 'north' => 53.40, 'east' => -2.60];

    public function __construct(private Api $api)
    {
    }

    public function isEnabled(): bool
    {
        return $this->api->isEnabled(Api::FLOOD_WARNINGS);
    }

    /**
     * Every flood alert and warning in force in Wales, in one small response.
     *
     * @return Collection<int, FloodWarningData>
     */
    public function floodWarnings(): Collection
    {
        $body = $this->api->get(Api::FLOOD_WARNINGS, 'floodwarnings/v3/all');

        return collect(is_array($body) && is_array($body['features'] ?? null) ? $body['features'] : [])
            ->map(fn (mixed $feature) => is_array($feature) && is_array($feature['properties'] ?? null) ? $feature['properties'] : null)
            ->filter(fn (?array $properties) => isset($properties['FWACODE']) && $properties['FWACODE'] !== '')
            ->map(fn (array $properties) => FloodWarningData::fromNaturalResourcesWales($properties))
            ->values();
    }

    /**
     * Whether a place could sit in or beside one of NRW's areas.
     */
    public function covers(float $latitude, float $longitude): bool
    {
        return $latitude >= self::AREA_EXTENT['south'] && $latitude <= self::AREA_EXTENT['north']
            && $longitude >= self::AREA_EXTENT['west'] && $longitude <= self::AREA_EXTENT['east'];
    }

    /**
     * NRW alert and warning areas whose outline comes within a radius of a point.
     *
     * @return Collection<int, FloodAreaData>
     */
    public function floodAreasNear(float $latitude, float $longitude, float $radiusKm): Collection
    {
        return collect(self::AREA_LAYERS)
            ->flatMap(function (string $layer) use ($latitude, $longitude, $radiusKm) {
                /** @var array<int, array<string, mixed>> $features */
                $features = $this->request()
                    ->get(config('services.nrw.flood_areas_url'), [
                        'service' => 'WFS',
                        'version' => '2.0.0',
                        'request' => 'GetFeature',
                        'typeNames' => $layer,
                        'outputFormat' => 'application/json',
                        'propertyName' => 'fwd_tacode,fwa_name,descrip,river_sea',
                        'cql_filter' => sprintf('DWITHIN(geom,SRID=4326;POINT(%F %F),%d,meters)', $longitude, $latitude, (int) round($radiusKm * 1000)),
                    ])
                    ->throw()
                    ->json('features', []);

                return collect($features)
                    ->map(fn (array $feature) => $feature['properties'] ?? null)
                    ->filter(fn (mixed $properties) => is_array($properties) && is_string($properties['fwd_tacode'] ?? null) && $properties['fwd_tacode'] !== '')
                    ->map(fn (array $properties) => FloodAreaData::fromNaturalResourcesWales($properties));
            })
            ->values();
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(60)
            ->retry(2, 2000)
            ->withUserAgent(config('services.user_agent'));
    }
}
