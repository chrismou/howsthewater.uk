<?php

namespace App\Services\Conditions;

use App\Data\FeedItem;
use App\Data\FloodWarningSummary;
use App\Data\SpotConditions;
use App\Data\UpstreamMonitorSummary;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use Illuminate\Support\Collection;

/**
 * Builds the RSS items for a spot or a river: each upstream overflow
 * starting and stopping, and each flood alert or warning in force. Items
 * describe what the monitors report, never whether the water is safe.
 */
class ConditionsFeed
{
    public const MAX_ITEMS = 50;

    private const HISTORY_DAYS = 30;

    public function __construct(
        private SpotConditionsService $conditions,
        private RiverOverviewService $overviews,
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
            ->flatMap(fn (SpillEvent $event) => $this->spillItems($event, $link, $distances->get($event->overflow_monitor_id)));

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
            ->flatMap(fn (SpillEvent $event) => $this->spillItems($event, $link, null));

        $spots = $river->spots()->published()->get()->each(fn (Spot $spot) => $spot->setRelation('river', $river));
        $floodWarnings = $this->conditions->forMany($spots)
            ->flatMap(fn (SpotConditions $conditions) => $conditions->floodWarnings)
            ->unique('areaCode');

        return $this->latest($spillItems->concat($this->floodItems($floodWarnings, $link)));
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
     * One item when an overflow starts discharging and another when it stops.
     *
     * @return array<int, FeedItem>
     */
    private function spillItems(SpillEvent $event, string $link, ?int $distanceUpstreamMetres): array
    {
        $monitor = $event->overflowMonitor;
        $where = ($distanceUpstreamMetres === null ? '' : ', '.number_format($distanceUpstreamMetres / 1000, 1).' km upstream')
            .($monitor->river === null ? '' : ' on the '.$monitor->river->name);

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
                title: $this->xmlText("Storm overflow {$monitor->external_id} stopped discharging after {$this->duration($event->durationMinutes())}"),
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
                description: $this->xmlText(trim($warning->severity->meaning().' '.($warning->message ?? '').' Read the latest on GOV.UK: '.$warning->url())),
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

    private function duration(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} min";
        }

        if ($minutes < 24 * 60) {
            return sprintf('%d h %d min', intdiv($minutes, 60), $minutes % 60);
        }

        $days = intdiv($minutes, 24 * 60);

        return sprintf('%d %s %d h', $days, $days === 1 ? 'day' : 'days', intdiv($minutes % (24 * 60), 60));
    }

    private function timezone(): string
    {
        return config('app.display_timezone', 'Europe/London');
    }
}
