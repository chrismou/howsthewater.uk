<?php

namespace App\Data;

use App\Enums\Agency;
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
     * @param  Collection<int, SpillHistoryDay>  $dailyHistory  Spills on the river and its tributaries, oldest day first
     * @param  array<string, float|null>|null  $dailyRainfall  Rain per UK day at the river's rain gauge, null without one
     * @param  list<Agency>  $floodAreaAgencies  The agencies whose flood areas cover the river's spots; empty where none is linked yet
     * @param  FloodWarningEventSummary|null  $lastFloodWarning  The last alert or warning lifted for one of its spots
     * @param  Carbon|null  $monitorsUpdatedAt  When the river's own monitors last reported, spots or no spots
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
        public Collection $dailyHistory = new Collection(),
        public ?array $dailyRainfall = null,
        public ?string $rainGaugeLabel = null,
        public array $floodAreaAgencies = [],
        public ?FloodWarningEventSummary $lastFloodWarning = null,
        public ?Carbon $monitorsUpdatedAt = null,
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
            dailyHistory: $overview->dailyHistory,
            dailyRainfall: $overview->dailyRainfall,
            rainGaugeLabel: $overview->rainGaugeLabel,
            floodAreaAgencies: $overview->floodAreaAgencies,
            lastFloodWarning: $overview->lastFloodWarning,
            monitorsUpdatedAt: $overview->monitorsUpdatedAt,
        );
    }

    /**
     * Every flood alert and warning in force for the river's spots, most severe first, each once.
     *
     * @return Collection<int, FloodWarningSummary>
     */
    public function floodWarnings(): Collection
    {
        return $this->spots
            ->flatMap(fn (SpotSummary $spot) => $spot->floodWarnings)
            ->unique('areaCode')
            ->sortBy(fn (FloodWarningSummary $warning) => $warning->severity->value)
            ->values();
    }

    /**
     * The spots whose gauge is linked, one per gauge, source to mouth, so a river's levels are listed once each.
     *
     * @return Collection<int, SpotSummary>
     */
    public function spotsWithOwnGauge(): Collection
    {
        return $this->spots
            ->filter(fn (SpotSummary $spot) => $spot->gaugeId !== null)
            ->unique('gaugeId')
            ->values();
    }

    /**
     * The spot halfway along the river, for a forecast that stands for the whole of it.
     */
    public function middleSpot(): ?SpotSummary
    {
        return $this->spots->get(intdiv(max(0, $this->spots->count() - 1), 2));
    }

    /**
     * When the river's monitor data last updated: the latest of its monitors' and its spots'.
     */
    public function dataUpdatedAt(): ?Carbon
    {
        return $this->spots->map(fn (SpotSummary $spot) => $spot->dataUpdatedAt)->push($this->monitorsUpdatedAt)->filter()->max();
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
