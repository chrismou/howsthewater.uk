<?php

namespace App\Data;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A main river and its spots, source to mouth, for region listings.
 */
final readonly class RiverSummary
{
    /**
     * Monitor and spill counts cover the river and all its tributaries.
     *
     * @param  Collection<int, SpotSummary>  $spots  Source to mouth
     * @param  list<string>  $companies  The water companies whose monitors these are, by name
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $catchment,
        public ?int $lengthMetres,
        public int $monitorCount,
        public int $dischargingNowCount,
        public Collection $spots,
        public int $offlineCount = 0,
        public int $spillsLast48HoursCount = 0,
        public bool $hasTributaries = false,
        public array $companies = [],
    ) {
    }

    public static function fromOverview(RiverOverview $overview): self
    {
        return new self(
            id: $overview->river->id,
            name: $overview->river->name,
            slug: $overview->river->slug,
            catchment: $overview->river->catchment,
            lengthMetres: $overview->river->length_metres,
            monitorCount: $overview->monitorCount,
            dischargingNowCount: $overview->dischargingNowCount,
            spots: $overview->spots->map(fn (SpotConditions $conditions) => SpotSummary::fromConditions($conditions))->values(),
            offlineCount: $overview->offlineCount,
            spillsLast48HoursCount: $overview->spillsLast48HoursCount,
            hasTributaries: $overview->hasTributaries,
            companies: $overview->companies,
        );
    }

    /**
     * When the river's monitor data last updated: the latest of its spots'.
     */
    public function dataUpdatedAt(): ?Carbon
    {
        return $this->spots->map(fn (SpotSummary $spot) => $spot->dataUpdatedAt)->filter()->max();
    }

    /**
     * Mirrors RiverOverview::overflowDataIsStale(), evaluated at read time.
     */
    public function overflowDataIsStale(): bool
    {
        $updatedAt = $this->dataUpdatedAt();

        return $this->monitorCount > 0
            && ($updatedAt === null || $updatedAt->lessThan(now()->subHours(SpotConditions::STALE_AFTER_HOURS)));
    }
}
