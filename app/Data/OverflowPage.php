<?php

namespace App\Data;

use App\Models\OverflowAnnualReturn;
use App\Models\OverflowMonitor;
use App\Models\OverflowMonitorPlace;
use App\Models\RainfallStation;
use App\Models\Region;
use App\Models\SpillEvent;
use App\Models\Spot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Everything an overflow's page shows apart from its paged spill log: its spills (from the live feed since we started
 * reading it, and the company's published log before that), the company's official annual returns, the last 30 days
 * beside the nearest rain gauge's rainfall, and the places whose pages show it. A spill still open on a monitor that
 * has stopped reporting counts only up to its last report.
 */
final readonly class OverflowPage
{
    /**
     * @param  Collection<int, OverflowMonitorPlace>  $places  Nearest first, each place loaded
     * @param  array{7: array{spills: int, hours: float}, 30: array{spills: int, hours: float}, year: array{spills: int, hours: float}}  $record  The last 7 and 30 UK days, and the year from $yearFrom
     * @param  Carbon  $recordingSince  When we started reading its live feed
     * @param  Carbon  $yearFrom  The start of the year, or of the spills we hold if they start later
     * @param  Collection<int, SpillHistoryDay>  $dailyHistory  Oldest first, UK days
     * @param  array<string, float|null>|null  $dailyRainfall  Millimetres by UK date, oldest first; null for a day with no readings
     * @param  Collection<int, OverflowAnnualReturn>  $annualReturns  Newest first
     * @param  list<array{0: float, 1: float}>|null  $centreline  The river's line for the map, where it is on one
     * @param  list<array{month: Carbon, spills: int, hours: float, recorded: bool, rain: float|null}>  $months  The last 12 UK months, oldest first
     * @param  Carbon  $coveredFrom  The earliest time we hold its spills for: its company's published log, or the record start
     * @param  list<string>  $notes  Where the spills shown come from and which months are missing (SpillCoverage::notes())
     */
    public function __construct(
        public OverflowMonitor $monitor,
        public Region $region,
        public Collection $places,
        public Carbon $recordingSince,
        public array $record,
        public Carbon $yearFrom,
        public int $totalSpills,
        public ?int $longestSpillMinutes,
        public Collection $dailyHistory,
        public ?RainfallStation $rainfallStation,
        public ?array $dailyRainfall,
        public Collection $annualReturns,
        public ?float $uptimePercent,
        public ?Carbon $uptimeSince,
        public ?array $centreline,
        public array $months,
        public Carbon $coveredFrom,
        public array $notes,
    ) {
    }

    /**
     * The name the page goes by: the overflow's own, else "Storm overflow YWS02215".
     */
    public function name(): string
    {
        return $this->monitor->titleName();
    }

    public function company(): string
    {
        return $this->monitor->companyName();
    }

    public function intoPhrase(): ?string
    {
        return $this->monitor->intoPhrase();
    }

    /**
     * The longest title that fits search results' 60 characters. An unnamed overflow already says "Storm overflow".
     */
    public function title(): string
    {
        $name = $this->name();
        $topic = $this->monitor->displayName() === null ? 'spills' : 'storm overflow spills';
        $into = $this->monitor->intoName();

        return collect([$into !== null ? "{$name}, {$into}: {$topic}" : null, "{$name}: {$topic}", $name])
            ->filter()
            ->first(fn (string $candidate) => mb_strlen($candidate) <= 60, Str::limit($name, 57));
    }

    /**
     * The overflow's own facts, so no two descriptions read alike, within 160 characters.
     */
    public function description(): string
    {
        $latestReturn = $this->annualReturns->first();
        $returnFact = $latestReturn?->counted_spills !== null ? ", {$latestReturn->counted_spills} counted in its {$latestReturn->year} return" : '';
        $reaches = match (true) {
            $this->spots()->isNotEmpty() => ', and the spots downstream',
            $this->waterBodies()->isNotEmpty() => ', and the beaches and lakes nearby',
            default => '',
        };
        $into = $this->intoPhrase() !== null ? ' '.$this->intoPhrase() : '';
        $intro = "{$this->name()}{$into}, {$this->monitor->companyPhrase()}: each spill as its monitor reports it";

        return collect(["{$intro}{$returnFact}{$reaches}.", "{$intro}{$returnFact}.", "{$this->name()}, {$this->monitor->companyPhrase()}: each spill as its monitor reports it."])
            ->first(fn (string $candidate) => mb_strlen($candidate) <= 160, Str::limit("{$this->name()}, {$this->monitor->companyPhrase()}.", 157));
    }

    /**
     * @return Collection<int, OverflowMonitorPlace>
     */
    public function spots(): Collection
    {
        return $this->places->filter(fn (OverflowMonitorPlace $link) => $link->place instanceof Spot)->values();
    }

    /**
     * @return Collection<int, OverflowMonitorPlace>
     */
    public function waterBodies(): Collection
    {
        return $this->places->reject(fn (OverflowMonitorPlace $link) => $link->place instanceof Spot)->values();
    }

    /**
     * "2026 so far", or "Since 13 Oct" when the spills we hold start this year, so a part year never reads as a whole one.
     */
    public function yearLabel(): string
    {
        $timezone = (string) config('app.display_timezone', 'Europe/London');
        $from = $this->yearFrom->copy()->timezone($timezone);

        return $from->isSameDay($from->copy()->startOfYear()) ? $from->year.' so far' : 'Since '.$from->format('j M');
    }

    /**
     * UK days in the last 30 on which a spill was running.
     */
    public function daysWithSpillLast30(): int
    {
        return $this->dailyHistory->filter(fn (SpillHistoryDay $day) => $day->spillCount > 0)->count();
    }

    public function longestSpillLabel(): ?string
    {
        return $this->longestSpillMinutes === null ? null : SpillEvent::labelForMinutes($this->longestSpillMinutes);
    }

    /**
     * Whether the page has anything of its own to say yet. A page with no name, no annual return and no spill recorded
     * is kept out of search results.
     */
    public function hasContent(): bool
    {
        return $this->monitor->displayName() !== null || $this->annualReturns->isNotEmpty() || $this->totalSpills > 0;
    }
}
