<?php

namespace App\Data;

use App\Enums\SpillRecency;

/**
 * A spot, beach or lake linked from another place's page, with how it
 * relates ("Upstream", "Beach") and how far away it is.
 */
final readonly class NearbyPlace
{
    /**
     * @param  bool  $alongRiver  Whether the distance follows the water rather than a straight line
     * @param  bool  $nearbyWording  Whether the spill status is for overflows nearby (beaches, lakes) rather than upstream
     */
    public function __construct(
        public string $relation,
        public string $name,
        public string $url,
        public int $distanceMetres,
        public bool $alongRiver,
        public SpillRecency $spillRecency,
        public bool $stale,
        public bool $nearbyWording,
    ) {
    }

    public function distanceLabel(): string
    {
        return number_format($this->distanceMetres / 1000, 1).' km'.($this->alongRiver ? ' along the river' : ' away');
    }
}
