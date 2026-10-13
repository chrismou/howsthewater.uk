<?php

namespace App\Services\Osm;

use App\Services\Geo\Coordinates;

/**
 * Joins an unordered set of OSM ways into one polyline by repeatedly
 * attaching the way whose end sits closest to the chain's current tail,
 * then orients the result so it runs downstream (OSM river ways are drawn
 * source to mouth, so the majority direction is trusted).
 */
class WayChainer
{
    private const JOIN_TOLERANCE_METRES = 250;

    /**
     * @param  array<int, array<int, array{0: float, 1: float}>>  $ways
     * @return array<int, array{0: float, 1: float}>
     */
    public static function chain(array $ways): array
    {
        $remaining = $ways;
        $chain = array_shift($remaining);
        $reversedCount = 0;
        $joinedCount = 1;

        while ($remaining !== []) {
            $tail = end($chain);
            $head = $chain[0];

            $best = ['index' => 0, 'where' => 'tail', 'reverse' => false, 'distance' => INF];

            foreach ($remaining as $index => $way) {
                $candidates = [
                    ['index' => $index, 'where' => 'tail', 'reverse' => false, 'distance' => self::distance($tail, $way[0])],
                    ['index' => $index, 'where' => 'tail', 'reverse' => true, 'distance' => self::distance($tail, end($way))],
                    ['index' => $index, 'where' => 'head', 'reverse' => false, 'distance' => self::distance($head, end($way))],
                    ['index' => $index, 'where' => 'head', 'reverse' => true, 'distance' => self::distance($head, $way[0])],
                ];

                foreach ($candidates as $candidate) {
                    if ($candidate['distance'] < $best['distance']) {
                        $best = $candidate;
                    }
                }
            }

            if ($best['distance'] > self::JOIN_TOLERANCE_METRES) {
                break;
            }

            $way = $remaining[$best['index']];
            unset($remaining[$best['index']]);

            if ($best['reverse']) {
                $way = array_reverse($way);
                $reversedCount++;
            }

            $joinedCount++;

            $chain = $best['where'] === 'tail'
                ? array_merge($chain, array_slice($way, 1))
                : array_merge(array_slice($way, 0, -1), $chain);
        }

        if ($reversedCount * 2 > $joinedCount) {
            $chain = array_reverse($chain);
        }

        return array_values($chain);
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    private static function distance(array $a, array $b): float
    {
        return Coordinates::distanceMetres($a[0], $a[1], $b[0], $b[1]);
    }
}
