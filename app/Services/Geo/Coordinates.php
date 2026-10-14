<?php

namespace App\Services\Geo;

class Coordinates
{
    private const EARTH_RADIUS_METRES = 6371000;

    /**
     * Great-circle distance in metres between two lat/lng points.
     */
    public static function distanceMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $deltaPhi = deg2rad($lat2 - $lat1);
        $deltaLambda = deg2rad($lng2 - $lng1);

        $a = sin($deltaPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($deltaLambda / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METRES * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Whether the box around a set of lat/lng points is taller (north to
     * south) than it is wide (east to west) on the ground.
     *
     * @param  iterable<int, array{0: float, 1: float}>  $points
     */
    public static function isTallerThanWide(iterable $points): bool
    {
        $points = is_array($points) ? $points : iterator_to_array($points, false);

        if (count($points) < 2) {
            return false;
        }

        $latitudes = array_column($points, 0);
        $longitudes = array_column($points, 1);
        $middleLatitude = (min($latitudes) + max($latitudes)) / 2;

        $height = self::distanceMetres(min($latitudes), 0, max($latitudes), 0);
        $width = self::distanceMetres($middleLatitude, min($longitudes), $middleLatitude, max($longitudes));

        return $height > $width;
    }
}
