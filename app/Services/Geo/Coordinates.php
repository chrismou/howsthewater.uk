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
}
