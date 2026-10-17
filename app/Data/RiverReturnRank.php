<?php

namespace App\Data;

/**
 * Where a river stood among its region's rivers with annual returns in a year, by hours: "the most", "the 3rd most",
 * "the joint 2nd most". Only made where at least two rivers have returns.
 */
final readonly class RiverReturnRank
{
    public function __construct(
        public int $year,
        public int $position,
        public int $of,
        public bool $joint,
    ) {
    }

    /**
     * "the most hours of spilling", "the joint 3rd most hours of spilling".
     */
    public function phrase(): string
    {
        $place = $this->position === 1 ? '' : self::ordinal($this->position).' ';

        return 'the '.($this->joint ? 'joint ' : '').$place.'most hours of spilling';
    }

    public static function ordinal(int $number): string
    {
        $suffix = match (true) {
            in_array($number % 100, [11, 12, 13], true) => 'th',
            $number % 10 === 1 => 'st',
            $number % 10 === 2 => 'nd',
            $number % 10 === 3 => 'rd',
            default => 'th',
        };

        return $number.$suffix;
    }
}
