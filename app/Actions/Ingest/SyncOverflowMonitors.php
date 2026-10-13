<?php

namespace App\Actions\Ingest;

use App\Data\OverflowMonitorData;
use App\Enums\OverflowStatus;
use App\Models\OverflowMonitor;
use App\Models\SpillEvent;
use App\Services\Stream\StormOverflowClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pulls the current state of every storm overflow monitor and records any
 * new or changed spill events, so a history builds up over time.
 */
class SyncOverflowMonitors
{
    public function __construct(private StormOverflowClient $client) {}

    /**
     * @return array{monitors: int, events_opened: int, events_closed: int}
     */
    public function handle(): array
    {
        return $this->sync($this->client->all());
    }

    /**
     * @param  Collection<int, OverflowMonitorData>  $monitors
     * @return array{monitors: int, events_opened: int, events_closed: int}
     */
    public function sync(Collection $monitors): array
    {
        $existing = OverflowMonitor::query()
            ->with('openSpillEvent')
            ->get()
            ->keyBy('external_id');

        $opened = 0;
        $closed = 0;

        foreach ($monitors as $data) {
            /** @var OverflowMonitor|null $monitor */
            $monitor = $existing->get($data->externalId);

            if ($monitor === null) {
                $monitor = new OverflowMonitor(['external_id' => $data->externalId]);
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

        return ['monitors' => $monitors->count(), 'events_opened' => $opened, 'events_closed' => $closed];
    }

    /**
     * The feed only exposes the latest event per monitor, so each sync
     * records that event if unseen and closes any open event once the
     * feed reports an end time.
     *
     * @return array{0: int, 1: int}
     */
    private function reconcileSpillEvents(OverflowMonitor $monitor, OverflowMonitorData $data): array
    {
        $opened = 0;
        $closed = 0;

        if ($data->latestEventStartedAt !== null) {
            $event = SpillEvent::query()->firstOrNew([
                'overflow_monitor_id' => $monitor->id,
                'started_at' => $data->latestEventStartedAt,
            ]);

            if (! $event->exists) {
                $opened++;
            }

            $endedAt = $this->resolveEndedAt($data);

            if ($event->exists && $event->ended_at === null && $endedAt !== null) {
                $closed++;
            }

            $event->ended_at = $endedAt;
            $event->save();
        }

        /** @var SpillEvent|null $open */
        $open = $monitor->openSpillEvent;

        if ($open !== null && $data->status !== OverflowStatus::Discharging && ! $open->started_at->equalTo($data->latestEventStartedAt)) {
            $open->update(['ended_at' => $data->statusStartedAt ?? now()]);
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

        return null;
    }
}
