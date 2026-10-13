<?php

namespace App\Services\Conditions;

use App\Data\SpotConditions;
use App\Data\UpstreamMonitorSummary;
use App\Enums\OverflowStatus;
use App\Enums\SpillRecency;
use App\Models\OverflowMonitor;
use App\Models\SpillEvent;
use App\Models\Spot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the full picture for a spot: upstream overflow activity, recent
 * spill counts and the nearest gauge's level. Shared by web and API.
 */
class SpotConditionsService
{
    public function __construct(private LevelSummariser $levelSummariser) {}

    public function for(Spot $spot): SpotConditions
    {
        $spot->loadMissing(['river', 'gaugeStation']);

        $monitors = $this->upstreamMonitors($spot);

        $last48Hours = now()->subHours(48);
        $last30Days = now()->subDays(30);

        $dischargingNow = $monitors->filter(fn (UpstreamMonitorSummary $summary) => $summary->status === OverflowStatus::Discharging)->count();
        $spillsLast48Hours = $this->countSpillsSince($monitors, $last48Hours);
        $spillsLast30Days = $this->countSpillsSince($monitors, $last30Days);

        return new SpotConditions(
            spot: $spot,
            spillRecency: $this->spillRecency($monitors, $dischargingNow),
            upstreamMonitors: $monitors,
            dischargingNowCount: $dischargingNow,
            spillsLast48HoursCount: $spillsLast48Hours,
            spillsLast30DaysCount: $spillsLast30Days,
            level: $spot->gaugeStation !== null ? $this->levelSummariser->summarise($spot->gaugeStation) : null,
            dataUpdatedAt: $monitors->max(fn (UpstreamMonitorSummary $summary) => $summary->monitor->source_updated_at),
        );
    }

    /**
     * @return Collection<int, UpstreamMonitorSummary>
     */
    private function upstreamMonitors(Spot $spot): Collection
    {
        if ($spot->river_metres === null) {
            return collect();
        }

        return $spot->upstreamMonitors()
            ->with(['spillEvents' => fn ($query) => $query->limit(1)])
            ->get()
            ->map(fn (OverflowMonitor $monitor) => new UpstreamMonitorSummary(
                monitor: $monitor,
                distanceUpstreamMetres: max(0, $spot->river_metres - $monitor->river_metres),
                status: $monitor->status,
                latestEvent: $monitor->spillEvents->first(),
            ));
    }

    /**
     * @param  Collection<int, UpstreamMonitorSummary>  $monitors
     */
    private function countSpillsSince(Collection $monitors, Carbon $since): int
    {
        $ids = $monitors->map(fn (UpstreamMonitorSummary $summary) => $summary->monitor->id);

        if ($ids->isEmpty()) {
            return 0;
        }

        return SpillEvent::query()
            ->whereIn('overflow_monitor_id', $ids)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $since))
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
