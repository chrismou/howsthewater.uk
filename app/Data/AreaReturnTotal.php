<?php

namespace App\Data;

/**
 * One river's or region's overflows added up from the companies' annual returns for a year: how many overflows it
 * has, how many of them have a return with hours that year, their counted spills (12/24 hour method) and hours. Hours
 * are each overflow's own added together, so overflows spilling at the same time all count. Plain values only, so the
 * link is built where it is printed, for the host serving the page.
 */
final readonly class AreaReturnTotal
{
    public function __construct(
        public string $name,
        public string $regionSlug,
        public ?int $riverId,
        public ?string $riverSlug,
        public int $overflows,
        public int $reporting,
        public int $countedSpills,
        public float $hours,
    ) {
    }

    /**
     * The river's page, or for a region its spills page.
     */
    public function url(): string
    {
        return $this->riverSlug === null ? route('spills.region', $this->regionSlug) : route('rivers.show', [$this->regionSlug, $this->riverSlug]);
    }

    /**
     * Hours per overflow with a return, so rivers with many overflows compare fairly with those with few.
     */
    public function hoursPerOverflow(): ?float
    {
        return $this->reporting === 0 ? null : round($this->hours / $this->reporting, 1);
    }

    public function hasReturns(): bool
    {
        return $this->reporting > 0;
    }

    /**
     * @param  array{0: string, 1: string, 2: int|null, 3: string|null, 4: int, 5: int, 6: int, 7: float}  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(...$values);
    }

    /**
     * @return array{0: string, 1: string, 2: int|null, 3: string|null, 4: int, 5: int, 6: int, 7: float}
     */
    public function toArray(): array
    {
        return [$this->name, $this->regionSlug, $this->riverId, $this->riverSlug, $this->overflows, $this->reporting, $this->countedSpills, $this->hours];
    }
}
