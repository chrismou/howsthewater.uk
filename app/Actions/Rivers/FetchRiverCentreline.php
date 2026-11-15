<?php

namespace App\Actions\Rivers;

use App\Models\River;
use App\Services\Geo\PolylineProjector;
use App\Services\OrdnanceSurvey\OpenRiversReader;
use App\Services\Osm\OverpassClient;
use RuntimeException;

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
        $reader = new OpenRiversReader($geoPackagePath);
        $centreline = $reader->riverCentreline($river->os_name ?? $river->name, $parentCentreline, $river->mouthHint());

        if ($river->continues_along !== null) {
            // Rivers are fetched in seeding order, so the river it flows into is seeded first and already has its line.
            if ($river->flowsInto?->hasCentreline() !== true) {
                throw new RuntimeException("{$river->name} continues along {$river->continues_along}, but the river it flows into has no centreline yet.");
            }

            $centreline = $reader->continueAlong($centreline, $river->continues_along, $river->flowsInto->centreline);
        }

        return $this->store($river, $centreline);
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
