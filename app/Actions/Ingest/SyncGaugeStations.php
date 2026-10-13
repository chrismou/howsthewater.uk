<?php

namespace App\Actions\Ingest;

use App\Models\GaugeStation;
use App\Models\River;
use App\Services\EnvironmentAgency\FloodMonitoringClient;

/**
 * Discovers Environment Agency level gauges on each river we cover and
 * keeps their metadata current.
 */
class SyncGaugeStations
{
    public function __construct(private FloodMonitoringClient $client) {}

    /**
     * @return array{stations: int}
     */
    public function handle(River $river): array
    {
        $count = 0;

        foreach ($river->aliases ?? [] as $alias) {
            foreach ($this->client->stationsOnRiver($alias) as $data) {
                GaugeStation::query()->updateOrCreate(
                    ['external_id' => $data->externalId],
                    [
                        'river_id' => $river->id,
                        'label' => $data->label,
                        'river_name' => $data->riverName,
                        'town' => $data->town,
                        'latitude' => $data->latitude,
                        'longitude' => $data->longitude,
                        'measure_id' => $data->measureId,
                        'typical_range_low' => $data->typicalRangeLow,
                        'typical_range_high' => $data->typicalRangeHigh,
                        'is_active' => $data->isActive,
                    ],
                );

                $count++;
            }
        }

        return ['stations' => $count];
    }
}
