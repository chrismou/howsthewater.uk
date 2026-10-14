<?php

namespace App\Actions\Ingest;

use App\Models\GaugeStation;
use App\Models\Lake;
use App\Services\EnvironmentAgency\FloodMonitoringClient;

/**
 * Links each lake to the EA station that reads its level. The station is
 * stored like a river gauge (without a river), so the existing readings
 * sync keeps it up to date.
 */
class SyncLakeGauges
{
    public function __construct(private FloodMonitoringClient $client)
    {
    }

    /**
     * @return array{lakes: int}
     */
    public function handle(bool $onlyUnlinked = true): array
    {
        $lakes = Lake::query()
            ->whereNotNull('gauge_reference')
            ->when($onlyUnlinked, fn ($query) => $query->whereNull('gauge_station_id'))
            ->get();

        $linked = 0;

        foreach ($lakes as $lake) {
            $data = $this->client->station((string) $lake->gauge_reference);

            if ($data === null) {
                continue;
            }

            $station = GaugeStation::query()->updateOrCreate(['external_id' => $data->externalId], [
                'label' => $data->label,
                'river_name' => $data->riverName ?? $lake->name,
                'town' => $data->town,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'measure_id' => $data->measureId,
                'unit' => $data->unit,
                'typical_range_low' => $data->typicalRangeLow,
                'typical_range_high' => $data->typicalRangeHigh,
                'is_active' => $data->isActive,
            ]);

            $lake->update(['gauge_station_id' => $station->id]);
            $linked++;
        }

        return ['lakes' => $linked];
    }
}
