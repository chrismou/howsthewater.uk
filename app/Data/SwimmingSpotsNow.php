<?php

namespace App\Data;

use Illuminate\Support\Collection;

/**
 * The home page's "Right now at popular swimming spots": the spots where something is happening, most pressing
 * first, topped up with well-known spots when there are few.
 */
final readonly class SwimmingSpotsNow
{
    /**
     * @param  Collection<int, SwimmingSpotNow>  $spots  Happening first, then quiet well-known spots
     * @param  int  $popularCount  How many popular spots were checked
     */
    public function __construct(
        public Collection $spots,
        public int $popularCount,
    ) {
    }

    public function anythingHappening(): bool
    {
        return $this->spots->contains(fn (SwimmingSpotNow $spot) => $spot->isHappening());
    }
}
