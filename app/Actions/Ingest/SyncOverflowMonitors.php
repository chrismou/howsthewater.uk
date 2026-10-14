<?php

namespace App\Actions\Ingest;

use App\Data\OverflowMonitorData;
use App\Enums\OverflowStatus;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\SpillEvent;
use App\Services\Stream\StormOverflowClient;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls the current state of every storm overflow monitor and records any
 * new or changed spill events, so a history builds up over time.
 */
class SyncOverflowMonitors
{
    public function __construct(private StormOverflowClient $client) {}

    /**
     * Syncs every water company feed a region needs. A feed that fails is
     * logged and skipped, so one company's outage never stops the others;
     * its monitors simply go stale and the pages say so.
     *
     * @return array{monitors: int, monitors_added: int, events_opened: int, events_closed: int, feeds: int, failed: array<int, string>}
     */
    public function handle(): array
    {
        $totals = ['monitors' => 0, 'monitors_added' => 0, 'events_opened' => 0, 'events_closed' => 0, 'feeds' => 0, 'failed' => []];

        foreach (Region::overflowFeeds() as $key => $feed) {
            try {
                $result = $this->sync($this->client->all($feed['url']));
            } catch (Throwable $exception) {
                Log::warning("Storm overflow feed {$key} failed: {$exception->getMessage()}");
                $totals['failed'][] = $feed['name'];

                continue;
            }

            $totals['monitors'] += $result['monitors'];
            $totals['monitors_added'] += $result['monitors_added'];
            $totals['events_opened'] += $result['events_opened'];
            $totals['events_closed'] += $result['events_closed'];
            $totals['feeds']++;
        }

        return $totals;
    }

    /**
     * @param  Collection<int, OverflowMonitorData>  $monitors
     * @return array{monitors: int, monitors_added: int, events_opened: int, events_closed: int}
     */
    public function sync(Collection $monitors): array
    {
        $existing = OverflowMonitor::query()
            ->with('openSpillEvents')
            ->get()
            ->keyBy('external_id');

        $opened = 0;
        $closed = 0;
        $added = 0;

        foreach ($monitors as $data) {
            /** @var OverflowMonitor|null $monitor */
            $monitor = $existing->get($data->externalId);

            if ($monitor === null) {
                $monitor = new OverflowMonitor(['external_id' => $data->externalId]);
                $added++;
                $monitor->setRelation('openSpillEvents', new EloquentCollection);
            }

            $monitor->fill([
                'company' => $data->company,
                'receiving_watercourse' => $data->receivingWatercourse,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'status' => $data->status,
                'status_started_at' => $data->statusStartedAt,
                'latest_event_started_at' => $data->latestEventStartedAt,
                'latest_event_ended_at' => $data->latestEventEndedAt,
                'source_updated_at' => $data->sourceUpdatedAt,
            ])->save();

            [$openedHere, $closedHere] = $this->reconcileSpillEvents($monitor, $data);

            $opened += $openedHere;
            $closed += $closedHere;
        }

        return ['monitors' => $monitors->count(), 'monitors_added' => $added, 'events_opened' => $opened, 'events_closed' => $closed];
    }

    /**
     * The feed only exposes the latest event per monitor, so each sync
     * records that event if unseen, closes it once the monitor stops
     * discharging, and closes any older event still open. An event is never
     * left open on a monitor that is not discharging, even when the feed
     * gives no end time, or it would count as an ongoing spill forever.
     *
     * @return array{0: int, 1: int}
     */
    private function reconcileSpillEvents(OverflowMonitor $monitor, OverflowMonitorData $data): array
    {
        $opened = 0;
        $closed = 0;
        $latest = null;

        if ($data->latestEventStartedAt !== null) {
            $latest = SpillEvent::query()->firstOrNew([
                'overflow_monitor_id' => $monitor->id,
                'started_at' => $data->latestEventStartedAt,
            ]);

            $wasOpen = ! $latest->exists || $latest->ended_at === null;

            if (! $latest->exists) {
                $opened++;
            }

            $latest->ended_at = $this->resolveEndedAt($data);

            if ($latest->exists && $wasOpen && $latest->ended_at !== null) {
                $closed++;
            }

            $latest->save();
        }

        foreach ($monitor->openSpillEvents as $open) {
            if ($latest !== null && $open->is($latest)) {
                continue;
            }

            $open->update(['ended_at' => $this->fallbackEndedAt($data)->max($open->started_at)]);
            $closed++;
        }

        return [$opened, $closed];
    }

    private function resolveEndedAt(OverflowMonitorData $data): ?Carbon
    {
        if ($data->status === OverflowStatus::Discharging) {
            return null;
        }

        if ($data->latestEventEndedAt !== null && $data->latestEventStartedAt !== null
            && $data->latestEventEndedAt->greaterThanOrEqualTo($data->latestEventStartedAt)) {
            return $data->latestEventEndedAt;
        }

        return $this->fallbackEndedAt($data)->max($data->latestEventStartedAt);
    }

    /**
     * The best estimate of when an event ended when the feed does not say:
     * the start of the next event while discharging, otherwise when the
     * monitor's status last changed.
     */
    private function fallbackEndedAt(OverflowMonitorData $data): Carbon
    {
        if ($data->status === OverflowStatus::Discharging) {
            return $data->latestEventStartedAt ?? now();
        }

        return $data->statusStartedAt ?? $data->sourceUpdatedAt ?? now();
    }
}
