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
 * Everything an overflow's page shows apart from its paged spill log: our own record of its spills, the company's
 * official annual returns, the last 30 days beside the nearest rain gauge's rainfall, and the places whose pages show
 * it. A spill still open on a monitor that has stopped reporting counts only up to its last report.
 */
final readonly class OverflowPage
{
    /**
     * @param  Collection<int, OverflowMonitorPlace>  $places  Nearest first, each place loaded
     * @param  array{7: array{spills: int, hours: float}, 30: array{spills: int, hours: float}, year: array{spills: int, hours: float}}  $record
     * @param  Collection<int, SpillHistoryDay>  $dailyHistory  Oldest first, UK days
     * @param  array<string, float|null>|null  $dailyRainfall  Millimetres by UK date, oldest first; null for a day with no readings
     * @param  Collection<int, OverflowAnnualReturn>  $annualReturns  Newest first
     * @param  list<array{0: float, 1: float}>|null  $centreline  The river's line for the map, where it is on one
     */
    public function __construct(
        public OverflowMonitor $monitor,
        public Region $region,
        public Collection $places,
        public Carbon $recordingSince,
        public array $record,
        public int $totalSpills,
        public ?int $longestSpillMinutes,
        public Collection $dailyHistory,
        public ?RainfallStation $rainfallStation,
        public ?array $dailyRainfall,
        public Collection $annualReturns,
        public ?float $uptimePercent,
        public ?Carbon $uptimeSince,
        public ?array $centreline,
    ) {
    }

    /**
     * The name the page goes by: the overflow's own, else "Storm overflow YWS02215".
     */
    public function name(): string
    {
        return $this->monitor->displayName() ?? 'Storm overflow '.$this->monitor->external_id;
    }

    /**
     * The company without "Ltd", "plc" or "Services" ("Anglian Water Services" reads as Anglian Water).
     */
    public function company(): string
    {
        return (string) preg_replace('/\s+(Ltd|Limited|plc|Services)\.?$/i', '', $this->monitor->company);
    }

    /**
     * "into the River Wharfe", "into Thornton Beck", "into Loch Gairloch": "the" only before a river.
     */
    public function intoPhrase(): ?string
    {
        $into = $this->monitor->intoName();

        if ($into === null) {
            return null;
        }

        return 'into '.(preg_match('/^(River|Afon)\s/i', $into) === 1 ? 'the ' : '').$into;
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
        $intro = "{$this->name()}{$into}, a {$this->company()} storm overflow: each spill as its monitor reports it";

        return collect(["{$intro}{$returnFact}{$reaches}.", "{$intro}{$returnFact}.", "{$this->name()}, a {$this->company()} storm overflow: each spill as its monitor reports it."])
            ->first(fn (string $candidate) => mb_strlen($candidate) <= 160, Str::limit("{$this->name()}, a {$this->company()} storm overflow.", 157));
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
