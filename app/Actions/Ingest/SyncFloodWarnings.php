<?php

namespace App\Actions\Ingest;

use App\Data\FloodWarningData;
use App\Models\FloodArea;
use App\Models\FloodWarning;
use App\Services\EnvironmentAgency\FloodMonitoringClient;
use Illuminate\Support\Collection;

/**
 * Replaces the stored warnings with those in force now for the flood areas
 * our spots use. The feed lists every warning in England in one small
 * response, so each run reads it whole; a warning missing from the feed,
 * or marked no longer in force, has been lifted and is removed.
 */
class SyncFloodWarnings
{
    public function __construct(private FloodMonitoringClient $client)
    {
    }

    /**
     * @param  Collection<int, FloodWarningData>|null  $feed  The national feed, if already fetched
     * @return array{warnings: int}
     */
    public function handle(?Collection $feed = null): array
    {
        $inForce = ($feed ?? $this->client->floodWarnings())
            ->filter(fn (FloodWarningData $warning) => $warning->isInForce())
            ->keyBy('floodAreaCode');

        $areas = FloodArea::query()->whereHas('spots')->whereIn('code', $inForce->keys())->get();

        foreach ($areas as $area) {
            /** @var FloodWarningData $data */
            $data = $inForce[$area->code];

            FloodWarning::query()->updateOrCreate(
                ['flood_area_id' => $area->id],
                [
                    'severity_level' => $data->severity,
                    'message' => $data->message,
                    'raised_at' => $data->raisedAt,
                    'message_changed_at' => $data->messageChangedAt,
                    'checked_at' => now(),
                ],
            );
        }

        FloodWarning::query()->whereNotIn('flood_area_id', $areas->pluck('id'))->delete();

        return ['warnings' => $areas->count()];
    }
}
