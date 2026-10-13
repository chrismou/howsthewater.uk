<?php

namespace App\Services\Geo;

/**
 * Result of projecting a point onto a polyline.
 */
final readonly class PolylineProjection
{
    public function __construct(
        public int $distanceAlongMetres,
        public float $offsetMetres,
    ) {}
}
