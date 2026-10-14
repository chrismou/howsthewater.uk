<?php

namespace App\Data;

use Illuminate\Support\Collection;

/**
 * A main river and its spots, source to mouth, for region listings.
 */
final readonly class RiverSummary
{
    /**
     * @param  Collection<int, SpotSummary>  $spots  Source to mouth
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
        );
    }
}
