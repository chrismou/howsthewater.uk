<?php

namespace App\Services\Osm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetches river centrelines from OpenStreetMap via the Overpass API and
 * chains the relation's member ways into a single ordered polyline.
 */
class OverpassClient
{
    /**
     * @return array<int, array{0: float, 1: float}> Ordered [lat, lng] pairs from source to mouth
     */
    public function riverCentreline(string $riverName, int $relationId = 0): array
    {
        $query = $relationId > 0
            ? sprintf('[out:json][timeout:120];relation(%d);way(r);out geom;', $relationId)
            : sprintf('[out:json][timeout:120];relation["waterway"="river"]["name"="%s"];way(r);out geom;', addslashes($riverName));

        /** @var array<int, array<string, mixed>> $elements */
        $elements = Http::asForm()
            ->timeout(150)
            ->retry(2, 5000)
            ->withUserAgent(config('services.user_agent'))
            ->post(config('services.overpass.url'), ['data' => $query])
            ->throw()
            ->json('elements', []);

        $ways = collect($elements)
            ->filter(fn (array $element) => ($element['type'] ?? null) === 'way' && ! empty($element['geometry']))
            ->map(fn (array $way) => array_map(
                fn (array $node) => [(float) $node['lat'], (float) $node['lon']],
                $way['geometry'],
            ))
            ->values()
            ->all();

        if ($ways === []) {
            throw new RuntimeException("No OpenStreetMap river ways found for {$riverName}.");
        }

        return WayChainer::chain($ways);
    }
}
