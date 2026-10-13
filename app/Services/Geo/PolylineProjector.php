<?php

namespace App\Services\Geo;

/**
 * Projects points onto a river centreline to find how far along the river
 * they sit. Uses a local equirectangular approximation, which is accurate
 * to well under a metre at the segment lengths involved.
 */
class PolylineProjector
{
    /**
     * @param  array<int, array{0: float, 1: float}>  $polyline  Ordered [lat, lng] pairs
     */
    public function __construct(private array $polyline)
    {
        if (count($polyline) < 2) {
            throw new \InvalidArgumentException('A polyline needs at least two points.');
        }
    }

    public function project(float $lat, float $lng): PolylineProjection
    {
        $bestOffset = INF;
        $bestDistanceAlong = 0.0;
        $cumulative = 0.0;

        for ($i = 0, $segments = count($this->polyline) - 1; $i < $segments; $i++) {
            [$aLat, $aLng] = $this->polyline[$i];
            [$bLat, $bLng] = $this->polyline[$i + 1];

            $segmentLength = Coordinates::distanceMetres($aLat, $aLng, $bLat, $bLng);

            [$fraction, $offset] = $this->projectOntoSegment($lat, $lng, $aLat, $aLng, $bLat, $bLng);

            if ($offset < $bestOffset) {
                $bestOffset = $offset;
                $bestDistanceAlong = $cumulative + $fraction * $segmentLength;
            }

            $cumulative += $segmentLength;
        }

        return new PolylineProjection((int) round($bestDistanceAlong), $bestOffset);
    }

    public function lengthMetres(): int
    {
        $total = 0.0;

        for ($i = 0, $segments = count($this->polyline) - 1; $i < $segments; $i++) {
            $total += Coordinates::distanceMetres(
                $this->polyline[$i][0],
                $this->polyline[$i][1],
                $this->polyline[$i + 1][0],
                $this->polyline[$i + 1][1],
            );
        }

        return (int) round($total);
    }

    /**
     * @return array{0: float, 1: float} Fraction along the segment (0..1) and perpendicular offset in metres
     */
    private function projectOntoSegment(float $pLat, float $pLng, float $aLat, float $aLng, float $bLat, float $bLng): array
    {
        $metresPerDegreeLat = 111320.0;
        $metresPerDegreeLng = 111320.0 * cos(deg2rad($aLat));

        $ax = 0.0;
        $ay = 0.0;
        $bx = ($bLng - $aLng) * $metresPerDegreeLng;
        $by = ($bLat - $aLat) * $metresPerDegreeLat;
        $px = ($pLng - $aLng) * $metresPerDegreeLng;
        $py = ($pLat - $aLat) * $metresPerDegreeLat;

        $segmentLengthSquared = $bx * $bx + $by * $by;

        if ($segmentLengthSquared === 0.0) {
            return [0.0, sqrt($px * $px + $py * $py)];
        }

        $fraction = (($px - $ax) * ($bx - $ax) + ($py - $ay) * ($by - $ay)) / $segmentLengthSquared;
        $fraction = max(0.0, min(1.0, $fraction));

        $closestX = $ax + $fraction * ($bx - $ax);
        $closestY = $ay + $fraction * ($by - $ay);

        $offset = sqrt(($px - $closestX) ** 2 + ($py - $closestY) ** 2);

        return [$fraction, $offset];
    }
}
