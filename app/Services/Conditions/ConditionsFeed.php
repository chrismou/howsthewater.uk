<?php

namespace App\Services\Conditions;

use App\Data\BathingWaterSummary;
use App\Data\FeedItem;
use App\Data\FloodWarningSummary;
use App\Data\NearbyMonitorSummary;
use App\Data\NearbyOverflows;
use App\Data\SpotConditions;
use App\Data\UpstreamMonitorSummary;
use App\Enums\PollutionRisk;
use App\Models\BathingWater;
use App\Models\BathingWaterSample;
use App\Models\Lake;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use Illuminate\Support\Collection;

/**
 * Builds the RSS items for a spot or a river (each upstream overflow
 * starting and stopping, and each flood alert or warning in force) and for
 * a beach or lake (each nearby overflow starting and stopping, each new
 * bathing water sample and each increased pollution risk forecast). Items
 * describe what the monitors report, never whether the water is safe.
 */
class ConditionsFeed
{
    public const MAX_ITEMS = 50;

    private const HISTORY_DAYS = 30;

    public function __construct(
        private SpotConditionsService $conditions,
        private RiverOverviewService $overviews,
        private WaterBodyConditionsService $waterBodies,
    ) {
    }

    /**
     * @return Collection<int, FeedItem>
     */
    public function forSpot(Spot $spot): Collection
    {
        $conditions = $this->conditions->for($spot);
        $link = route('spots.show', [$spot->river->region, $spot->river, $spot]);

        $distances = $conditions->upstreamMonitors->mapWithKeys(fn (UpstreamMonitorSummary $summary) => [$summary->monitor->id => $summary->distanceUpstreamMetres]);

        $spillItems = $conditions->recentSpillEvents
            ->flatMap(fn (SpillEvent $event) => $this->spillItems($event, $link, $this->distance($distances->get($event->overflow_monitor_id), 'upstream')));

        return $this->latest($spillItems->concat($this->floodItems($conditions->floodWarnings, $link)));
    }

    /**
     * Overflows on the river and every tributary, plus the flood warnings
     * covering any of its spots.
     *
     * @return Collection<int, FeedItem>
     */
    public function forRiver(River $river): Collection
    {
        $link = route('rivers.show', [$river->region, $river]);
        $since = now()->subDays(self::HISTORY_DAYS);

        $spillItems = SpillEvent::query()
            ->whereHas('overflowMonitor', fn ($query) => $query->whereIn('river_id', $this->overviews->riverFamilyIds($river)))
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $since))
            ->with(['overflowMonitor.river' => fn ($query) => $query->withoutCentreline()])
            ->orderByDesc('started_at')
            ->limit(self::MAX_ITEMS)
            ->get()
            ->flatMap(fn (SpillEvent $event) => $this->spillItems($event, $link, ''));

        $spots = $river->spots()->published()->get()->each(fn (Spot $spot) => $spot->setRelation('river', $river));
        $floodWarnings = $this->conditions->forMany($spots)
            ->flatMap(fn (SpotConditions $conditions) => $conditions->floodWarnings)
            ->unique('areaCode');

        return $this->latest($spillItems->concat($this->floodItems($floodWarnings, $link)));
    }

    /**
     * Overflows within the nearby radius, plus the beach's samples and forecasts.
     *
     * @return Collection<int, FeedItem>
     */
    public function forBeach(BathingWater $beach): Collection
    {
        $conditions = $this->waterBodies->forBeach($beach);
        $link = route('beaches.show', [$beach->region, $beach]);

        return $this->latest($this->nearbySpillItems($conditions->overflows, $link)->concat($this->bathingWaterItems($conditions->bathingWater, $link, null)));
    }

    /**
     * Overflows near the lake and its bathing sites, plus each site's samples
     * and forecasts, named so readers can tell the sites apart.
     *
     * @return Collection<int, FeedItem>
     */
    public function forLake(Lake $lake): Collection
    {
        $conditions = $this->waterBodies->forLake($lake);
        $link = route('lakes.show', [$lake->region, $lake]);

        $bathingItems = $conditions->bathingWaters->flatMap(fn (BathingWaterSummary $summary) => $this->bathingWaterItems($summary, $link, $summary->bathingWater->name));

        return $this->latest($this->nearbySpillItems($conditions->overflows, $link)->concat($bathingItems));
    }

    /**
     * @param  Collection<int, FeedItem>  $items
     * @return Collection<int, FeedItem>
     */
    private function latest(Collection $items): Collection
    {
        return $items->sortByDesc(fn (FeedItem $item) => $item->publishedAt->getTimestamp())->take(self::MAX_ITEMS)->values();
    }

    /**
     * @return Collection<int, FeedItem>
     */
    private function nearbySpillItems(NearbyOverflows $overflows, string $link): Collection
    {
        $distances = $overflows->monitors->mapWithKeys(fn (NearbyMonitorSummary $summary) => [$summary->monitor->id => $summary->distanceMetres]);

        return $overflows->recentSpillEvents
            ->flatMap(fn (SpillEvent $event) => $this->spillItems($event, $link, $this->distance($distances->get($event->overflow_monitor_id), 'away')));
    }

    /**
     * One item per sample taken in the last 30 days, and one for an
     * increased pollution risk forecast while it is in force.
     *
     * @return Collection<int, FeedItem>
     */
    private function bathingWaterItems(BathingWaterSummary $summary, string $link, ?string $site): Collection
    {
        $bathingWater = $summary->bathingWater;
        $at = $site === null ? '' : " at {$site}";
        $since = now()->subDays(self::HISTORY_DAYS);

        $items = $summary->recentSamples
            ->filter(fn (BathingWaterSample $sample) => $sample->sampled_at->greaterThanOrEqualTo($since))
            ->map(function (BathingWaterSample $sample) use ($at, $link, $summary) {
                $counts = collect([
                    'E. coli' => BathingWaterSample::formatCount($sample->e_coli, $sample->e_coli_qualifier),
                    'intestinal enterococci' => BathingWaterSample::formatCount($sample->enterococci, $sample->enterococci_qualifier),
                ])->filter()->map(fn (string $count, string $name) => "{$name} {$count}")->join(' and ');

                return new FeedItem(
                    guid: "sample-{$sample->id}",
                    title: $this->xmlText("Bathing water sample{$at} taken ".$sample->sampled_at->timezone($this->timezone())->format('j M')),
                    description: $this->xmlText('The Environment Agency sample '.($counts === '' ? 'has no counts reported yet.' : "counted {$counts} per 100 ml.").' Read the full results: '.$summary->url()),
                    link: $link,
                    publishedAt: $sample->sampled_at,
                );
            })
            ->values();

        if ($summary->currentRisk() === PollutionRisk::Increased && $bathingWater->risk_expires_at !== null) {
            $items->push(new FeedItem(
                guid: "risk-{$bathingWater->id}-{$bathingWater->risk_expires_at->getTimestamp()}",
                title: $this->xmlText(PollutionRisk::Increased->label().$at),
                description: $this->xmlText('The Environment Agency forecasts an increased risk of pollution'.$at.', usually after heavy rain, until '.$bathingWater->risk_expires_at->timezone($this->timezone())->format('j M, H:i').'. Read the forecast: '.$summary->url()),
                link: $link,
                // Forecasts are issued daily and only their expiry is kept, so date the item a day before it.
                publishedAt: $bathingWater->risk_expires_at->copy()->subDay(),
            ));
        }

        return $items;
    }

    /**
     * ", 2.4 km upstream" or ", 1.2 km away", or nothing when the distance is unknown.
     */
    private function distance(?int $metres, string $direction): string
    {
        return $metres === null ? '' : ', '.number_format($metres / 1000, 1)." km {$direction}";
    }

    /**
     * One item when an overflow starts discharging and another when it stops.
     *
     * @return array<int, FeedItem>
     */
    private function spillItems(SpillEvent $event, string $link, string $distance): array
    {
        $monitor = $event->overflowMonitor;
        $where = $distance.($monitor->river === null ? '' : ' on the '.$monitor->river->name);

        $items = [new FeedItem(
            guid: "spill-{$event->id}-start",
            title: $this->xmlText("Storm overflow {$monitor->external_id} started discharging{$where}"),
            description: $this->xmlText('The water company monitor reported this storm overflow discharging from '.$event->started_at->timezone($this->timezone())->format('j M, H:i').'.'),
            link: $link,
            publishedAt: $event->started_at,
        )];

        if ($event->ended_at !== null) {
            $items[] = new FeedItem(
                guid: "spill-{$event->id}-end",
                title: $this->xmlText("Storm overflow {$monitor->external_id} stopped discharging after {$event->durationLabel()}"),
                description: $this->xmlText('The water company monitor reported this storm overflow stopped discharging at '.$event->ended_at->timezone($this->timezone())->format('j M, H:i').'.'),
                link: $link,
                publishedAt: $event->ended_at,
            );
        }

        return $items;
    }

    /**
     * @param  Collection<int, FloodWarningSummary>  $warnings
     * @return Collection<int, FeedItem>
     */
    private function floodItems(Collection $warnings, string $link): Collection
    {
        return $warnings->map(function (FloodWarningSummary $warning) use ($link) {
            $issuedAt = $warning->messageChangedAt ?? $warning->raisedAt ?? $warning->checkedAt;

            return new FeedItem(
                guid: "flood-{$warning->areaCode}-{$issuedAt->getTimestamp()}",
                title: $this->xmlText("{$warning->severity->label()} in force: {$warning->areaLabel}"),
                description: $this->xmlText(trim($warning->severity->meaning().' '.($warning->message ?? '').' '.$warning->urlLabel().': '.$warning->url())),
                link: $link,
                publishedAt: $issuedAt,
            );
        })->values();
    }

    /**
     * Feed text with any character XML 1.0 forbids removed, so a stray
     * control character in an EA message cannot make the whole feed invalid.
     */
    private function xmlText(string $text): string
    {
        return (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);
    }

    private function timezone(): string
    {
        return config('app.display_timezone', 'Europe/London');
    }
}
