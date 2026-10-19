<?php

namespace App\Data;

use App\Enums\SpillRecency;

/**
 * A popular swimming spot (a designated bathing water or a well-known river spot) in the home page's "Right now"
 * list, with what is happening there, if anything.
 */
final readonly class SwimmingSpotNow
{
    /**
     * @param  string  $detail  Where it is ("River Wharfe, Yorkshire", "Beach, North East")
     * @param  bool  $nearbyWording  Whether the spill status is for overflows nearby (beaches, lakes) rather than upstream
     * @param  string|null  $note  What else is in force there (a pollution risk forecast, a flood warning)
     * @param  int|null  $rank  How pressing it is (PopularSwimmingSpots::RANK_*), or null when nothing is happening
     */
    public function __construct(
        public string $name,
        public string $detail,
        public string $url,
        public SpillRecency $spillRecency,
        public bool $stale,
        public bool $nearbyWording,
        public ?string $note,
        public ?int $rank,
        public string $kind,
    ) {
    }

    public function isHappening(): bool
    {
        return $this->rank !== null;
    }
}
