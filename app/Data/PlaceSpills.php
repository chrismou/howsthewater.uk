<?php

namespace App\Data;

use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\OverflowMonitor;
use App\Models\RainfallStation;
use App\Models\Region;
use App\Models\Spot;
use App\Services\Conditions\WaterBodyConditionsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The spill history of every overflow a place's page shows: those upstream of a spot, or within 3 km of a beach or
 * lake, since we started recording them. Hours are hours when at least one of them was spilling, so spills that
 * overlap count once; the busiest list gives each overflow's own hours.
 */
final readonly class PlaceSpills
{
    /**
     * @param  array<int, int>  $distances  Metres from the place, by monitor id: along the water for a spot
     * @param  array{7: array{spills: int, hours: float}, 30: array{spills: int, hours: float}, year: array{spills: int, hours: float}}  $record  The last 7 and 30 UK days, and the year from $yearFrom
     * @param  Collection<int, SpillHistoryDay>  $dailyHistory  Oldest first, UK days, minutes when any overflow was spilling
     * @param  array<string, float|null>|null  $dailyRainfall  Millimetres by UK date; null for a day with no readings
     * @param  list<array{month: Carbon, spills: int, hours: float}>  $months  Oldest first, from the month recording began
     * @param  list<array{monitor: OverflowMonitor, spills: int, hours: float}>  $busiest  Most hours first, from $yearFrom
     */
    public function __construct(
        public Spot|BathingWater|Lake $place,
        public Region $region,
        public array $distances,
        public ?Carbon $recordingSince,
        public ?Carbon $dataUpdatedAt,
        public array $record,
        public Carbon $yearFrom,
        public int $daysWithSpillLast30,
        public Collection $dailyHistory,
        public ?RainfallStation $rainfallStation,
        public ?array $dailyRainfall,
        public array $months,
        public array $busiest,
    ) {
    }

    public function monitorCount(): int
    {
        return count($this->distances);
    }

    public function isSpot(): bool
    {
        return $this->place instanceof Spot;
    }

    /**
     * "upstream" for a spot, "nearby" for a beach or lake, never the other way round.
     */
    public function relation(): string
    {
        return $this->isSpot() ? 'upstream' : 'nearby';
    }

    /**
     * Where the overflows are: "upstream of Ilkley, River Wharfe", "within 3 km of Runswick Bay".
     */
    public function where(): string
    {
        return $this->isSpot()
            ? "upstream of {$this->placeName()}"
            : 'within '.(WaterBodyConditionsService::NEARBY_RADIUS_METRES / 1000)." km of {$this->place->name}";
    }

    /**
     * The place as its page names it: "Ilkley, River Wharfe" for a spot.
     */
    public function placeName(): string
    {
        return $this->place instanceof Spot ? "{$this->place->name}, {$this->place->river->name}" : $this->place->name;
    }

    public function heading(): string
    {
        return $this->isSpot() ? "Spills upstream of {$this->place->name}" : "Spills near {$this->place->name}";
    }

    public function placeUrl(): string
    {
        return match (true) {
            $this->place instanceof Spot => route('spots.show', [$this->region, $this->place->river, $this->place]),
            $this->place instanceof BathingWater => route('beaches.show', [$this->region, $this->place]),
            default => route('lakes.show', [$this->region, $this->place]),
        };
    }

    public function url(): string
    {
        return match (true) {
            $this->place instanceof Spot => route('spots.spills', [$this->region, $this->place->river, $this->place]),
            $this->place instanceof BathingWater => route('beaches.spills', [$this->region, $this->place]),
            default => route('lakes.spills', [$this->region, $this->place]),
        };
    }

    /**
     * The place's own trail, then this page: the visible breadcrumb and the BreadcrumbList are both built from it.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function breadcrumbs(): array
    {
        $trail = match (true) {
            $this->place instanceof Spot => [['All rivers', route('hubs.rivers')], [$this->region->name, route('regions.show', $this->region)], ['Rivers', route('rivers.index', $this->region)], [$this->place->river->name, route('rivers.show', [$this->region, $this->place->river])]],
            $this->place instanceof BathingWater => [['All beaches', route('hubs.beaches')], [$this->region->name, route('regions.show', $this->region)], ['Beaches', route('beaches.index', $this->region)]],
            default => [['All lakes', route('hubs.lakes')], [$this->region->name, route('regions.show', $this->region)], ['Lakes', route('lakes.index', $this->region)]],
        };

        return [...$trail, [$this->place->name, $this->placeUrl()], ['Spills', $this->url()]];
    }

    /**
     * How far an overflow is from the place, "5.0 km".
     */
    public function distanceLabel(OverflowMonitor $monitor): string
    {
        return number_format(($this->distances[$monitor->id] ?? 0) / 1000, 1).' km';
    }

    /**
     * "2026 so far", or "Since 13 Oct" when our record began this year, so a part year never reads as a whole one.
     */
    public function yearLabel(): string
    {
        $from = $this->yearFrom->copy()->timezone($this->timezone());

        return $from->isSameDay($from->copy()->startOfYear()) ? $from->year.' so far' : 'Since '.$from->format('j M');
    }

    /**
     * Whether no overflow behind these figures has reported for SpotConditions::STALE_AFTER_HOURS.
     */
    public function dataIsStale(): bool
    {
        return $this->monitorCount() > 0
            && ($this->dataUpdatedAt === null || $this->dataUpdatedAt->lessThan(now()->subHours(SpotConditions::STALE_AFTER_HOURS)));
    }

    /**
     * The longest title that fits search results' 60 characters.
     */
    public function title(): string
    {
        $topic = $this->isSpot() ? 'spills upstream' : 'storm overflow spills nearby';

        return collect(["{$this->placeName()}: {$topic}", "{$this->place->name}: {$topic}", "{$this->place->name}: spills {$this->relation()}"])
            ->first(fn (string $candidate) => mb_strlen($candidate) <= 60, Str::limit($this->place->name, 57));
    }

    /**
     * The place's own facts, within 160 characters.
     */
    public function description(): string
    {
        if ($this->monitorCount() === 0) {
            return Str::limit("No storm overflow monitors have been matched {$this->where()} yet.", 157);
        }

        $monitors = $this->monitorCount().' storm '.Str::plural('overflow', $this->monitorCount());
        $since = $this->recordingSince ? ' since '.$this->recordingSince->copy()->timezone($this->timezone())->format('j F Y') : '';

        return collect([
            "Every spill from the {$monitors} {$this->where()}{$since}: hours spilling by month and the busiest overflows.",
            "Every spill from the {$monitors} {$this->where()}{$since}.",
            "Every spill from the storm overflows {$this->where()}.",
        ])->first(fn (string $candidate) => mb_strlen($candidate) <= 160, Str::limit("Every spill from the storm overflows {$this->where()}.", 157));
    }

    private function timezone(): string
    {
        return (string) config('app.display_timezone', 'Europe/London');
    }
}
