<?php

namespace App\Actions\Rivers;

use App\Models\River;
use App\Services\Geo\PolylineProjector;
use App\Services\OrdnanceSurvey\OpenRiversReader;
use App\Services\Osm\OverpassClient;

/**
 * Stores a river's centreline so points can be positioned along it. OS Open
 * Rivers is the preferred source; OpenStreetMap is the fallback when no
 * GeoPackage is available.
 */
class FetchRiverCentreline
{
    public function __construct(private OverpassClient $overpass)
    {
    }

    public function fromOpenRivers(River $river, string $geoPackagePath): River
    {
        $parentCentreline = $river->parent?->hasCentreline() === true ? $river->parent->centreline : null;

        return $this->store($river, (new OpenRiversReader($geoPackagePath))->riverCentreline($river->os_name ?? $river->name, $parentCentreline, $river->mouthHint()));
    }

    public function fromOpenStreetMap(River $river): River
    {
        return $this->store($river, $this->overpass->riverCentreline($river->os_name ?? $river->name));
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $centreline
     */
    private function store(River $river, array $centreline): River
    {
        $river->update([
            'centreline' => $centreline,
            'length_metres' => (new PolylineProjector($centreline))->lengthMetres(),
        ]);

        return $river;
    }
}
