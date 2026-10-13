<?php

namespace App\Services\Geo;

/**
 * Converts Ordnance Survey British National Grid eastings and northings
 * (OSGB36) to WGS84 latitude and longitude. Accurate to a few metres,
 * which is plenty for positioning points along a river.
 */
class BritishNationalGrid
{
    /**
     * @return array{0: float, 1: float} [latitude, longitude]
     */
    public static function toLatLng(float $easting, float $northing): array
    {
        [$phi, $lambda] = self::inverseTransverseMercator($easting, $northing);
        [$x, $y, $z] = self::toCartesian($phi, $lambda, 6377563.396, 6356256.909);
        [$x, $y, $z] = self::helmertToWgs84($x, $y, $z);
        [$lat, $lng] = self::toGeodetic($x, $y, $z, 6378137.0, 6356752.3142);

        return [round(rad2deg($lat), 6), round(rad2deg($lng), 6)];
    }

    /**
     * @return array{0: float, 1: float} [phi, lambda] in radians on the Airy 1830 ellipsoid
     */
    private static function inverseTransverseMercator(float $easting, float $northing): array
    {
        $a = 6377563.396;
        $b = 6356256.909;
        $f0 = 0.9996012717;
        $phi0 = deg2rad(49.0);
        $lambda0 = deg2rad(-2.0);
        $n0 = -100000.0;
        $e0 = 400000.0;

        $e2 = 1 - ($b * $b) / ($a * $a);
        $n = ($a - $b) / ($a + $b);

        $phi = $phi0;
        $m = 0.0;

        do {
            $phi = ($northing - $n0 - $m) / ($a * $f0) + $phi;

            $ma = (1 + $n + (5 / 4) * $n ** 2 + (5 / 4) * $n ** 3) * ($phi - $phi0);
            $mb = (3 * $n + 3 * $n ** 2 + (21 / 8) * $n ** 3) * sin($phi - $phi0) * cos($phi + $phi0);
            $mc = ((15 / 8) * $n ** 2 + (15 / 8) * $n ** 3) * sin(2 * ($phi - $phi0)) * cos(2 * ($phi + $phi0));
            $md = (35 / 24) * $n ** 3 * sin(3 * ($phi - $phi0)) * cos(3 * ($phi + $phi0));
            $m = $b * $f0 * ($ma - $mb + $mc - $md);
        } while ($northing - $n0 - $m >= 0.00001);

        $sinPhi = sin($phi);
        $cosPhi = cos($phi);
        $tanPhi = tan($phi);

        $nu = $a * $f0 / sqrt(1 - $e2 * $sinPhi ** 2);
        $rho = $a * $f0 * (1 - $e2) * (1 - $e2 * $sinPhi ** 2) ** -1.5;
        $eta2 = $nu / $rho - 1;

        $vii = $tanPhi / (2 * $rho * $nu);
        $viii = $tanPhi / (24 * $rho * $nu ** 3) * (5 + 3 * $tanPhi ** 2 + $eta2 - 9 * $tanPhi ** 2 * $eta2);
        $ix = $tanPhi / (720 * $rho * $nu ** 5) * (61 + 90 * $tanPhi ** 2 + 45 * $tanPhi ** 4);
        $x = 1 / ($cosPhi * $nu);
        $xi = 1 / ($cosPhi * 6 * $nu ** 3) * ($nu / $rho + 2 * $tanPhi ** 2);
        $xii = 1 / ($cosPhi * 120 * $nu ** 5) * (5 + 28 * $tanPhi ** 2 + 24 * $tanPhi ** 4);
        $xiia = 1 / ($cosPhi * 5040 * $nu ** 7) * (61 + 662 * $tanPhi ** 2 + 1320 * $tanPhi ** 4 + 720 * $tanPhi ** 6);

        $de = $easting - $e0;

        $latitude = $phi - $vii * $de ** 2 + $viii * $de ** 4 - $ix * $de ** 6;
        $longitude = $lambda0 + $x * $de - $xi * $de ** 3 + $xii * $de ** 5 - $xiia * $de ** 7;

        return [$latitude, $longitude];
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private static function toCartesian(float $phi, float $lambda, float $a, float $b): array
    {
        $e2 = 1 - ($b * $b) / ($a * $a);
        $nu = $a / sqrt(1 - $e2 * sin($phi) ** 2);

        return [
            $nu * cos($phi) * cos($lambda),
            $nu * cos($phi) * sin($lambda),
            (1 - $e2) * $nu * sin($phi),
        ];
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private static function helmertToWgs84(float $x, float $y, float $z): array
    {
        $tx = 446.448;
        $ty = -125.157;
        $tz = 542.060;
        $rx = deg2rad(0.1502 / 3600);
        $ry = deg2rad(0.2470 / 3600);
        $rz = deg2rad(0.8421 / 3600);
        $s = 1 + (-20.4894 / 1_000_000);

        return [
            $tx + $s * $x - $rz * $y + $ry * $z,
            $ty + $rz * $x + $s * $y - $rx * $z,
            $tz - $ry * $x + $rx * $y + $s * $z,
        ];
    }

    /**
     * @return array{0: float, 1: float} [phi, lambda] in radians
     */
    private static function toGeodetic(float $x, float $y, float $z, float $a, float $b): array
    {
        $e2 = 1 - ($b * $b) / ($a * $a);
        $p = sqrt($x * $x + $y * $y);
        $phi = atan2($z, $p * (1 - $e2));

        for ($i = 0; $i < 10; $i++) {
            $nu = $a / sqrt(1 - $e2 * sin($phi) ** 2);
            $phi = atan2($z + $e2 * $nu * sin($phi), $p);
        }

        return [$phi, atan2($y, $x)];
    }
}
