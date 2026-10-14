<?php

namespace App\Services\Conditions;

use App\Data\SpotConditions;
use App\Data\UpstreamMonitorSummary;
use App\Enums\OverflowStatus;
use App\Enums\SpillRecency;
use App\Models\SpillEvent;
use App\Models\Spot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the full picture for a spot: upstream overflow activity, recent
 * spill history and the nearest gauge's level. Shared by web and API.
 */
class SpotConditionsService
{
    public function __construct(
        private UpstreamMonitorFinder $upstreamMonitors,
        private SpillHistoryService $spillHistory,
        private LevelSummariser $levelSummariser,
        private RainfallSummariser $rainfallSummariser,
        private ConditionsCache $cache,
    ) {}

    public function for(Spot $spot): SpotConditions
    {
        return $this->cache->rememberSpot($spot, fn () => $this->build($spot));
    }

    /**
     * Computes conditions from the database, bypassing the cache.
     */
    public function build(Spot $spot): SpotConditions
    {
        $spot->loadMissing(['river.region', 'gaugeStation', 'rainfallStation']);

        $monitors = $this->upstreamMonitors->for($spot);
        $recentEvents = $this->spillHistory->recentEvents($monitors);

        $dischargingNow = $monitors->filter(fn (UpstreamMonitorSummary $summary) => $summary->status === OverflowStatus::Discharging)->count();

        return new SpotConditions(
            spot: $spot,
            spillRecency: $this->spillRecency($monitors, $dischargingNow),
            upstreamMonitors: $monitors,
            dischargingNowCount: $dischargingNow,
            spillsLast48HoursCount: $this->countEventsSince($recentEvents, now()->subHours(48)),
            spillsLast30DaysCount: $recentEvents->count(),
            recentSpillEvents: $recentEvents,
            dailyHistory: $this->spillHistory->dailyBreakdown($recentEvents),
            level: $spot->gaugeStation !== null ? $this->levelSummariser->summarise($spot->gaugeStation) : null,
            rainfall: $spot->rainfallStation !== null ? $this->rainfallSummariser->summarise($spot, $spot->rainfallStation) : null,
            dataUpdatedAt: $monitors->max(fn (UpstreamMonitorSummary $summary) => $summary->monitor->source_updated_at),
        );
    }

    /**
     * @param  Collection<int, SpillEvent>  $events
     */
    private function countEventsSince(Collection $events, Carbon $since): int
    {
        return $events
            ->filter(fn (SpillEvent $event) => $event->ended_at === null || $event->ended_at->greaterThanOrEqualTo($since))
            ->count();
    }

    /**
     * @param  Collection<int, UpstreamMonitorSummary>  $monitors
     */
    private function spillRecency(Collection $monitors, int $dischargingNow): SpillRecency
    {
        if ($monitors->isEmpty()) {
            return SpillRecency::NoData;
        }

        if ($dischargingNow > 0) {
            return SpillRecency::DischargingNow;
        }

        if ($monitors->contains(fn (UpstreamMonitorSummary $summary) => $summary->spilledSince(now()->subHours(24)))) {
            return SpillRecency::Within24Hours;
        }

        if ($monitors->contains(fn (UpstreamMonitorSummary $summary) => $summary->spilledSince(now()->subHours(48)))) {
            return SpillRecency::Within48Hours;
        }

        return SpillRecency::None;
    }
}
