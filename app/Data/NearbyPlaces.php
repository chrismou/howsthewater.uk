<?php

namespace App\Data;

use Illuminate\Support\Collection;

/**
 * The neighbours a place page links to, plus links back to the listings
 * it belongs to.
 */
final readonly class NearbyPlaces
{
    /**
     * @param  Collection<int, NearbyPlace>  $places
     * @param  list<array{0: string, 1: string}>  $links  [text, url] pairs
     */
    public function __construct(
        public Collection $places,
        public array $links,
    ) {
    }
}
