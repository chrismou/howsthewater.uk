<?php

use App\Models\GaugeReading;
use App\Models\GaugeStation;
use App\Services\Conditions\GaugeLevelHistory;

it('thins a week of readings to one an hour, keeping the latest in each hour and nothing older than the week', function () {
    $this->travelTo('2026-11-03 12:00:00');
    $station = GaugeStation::factory()->create();
    $other = GaugeStation::factory()->create();

    foreach ([['12:00', 0.5], ['11:45', 0.4], ['11:30', 0.3], ['10:15', 0.2]] as [$time, $value]) {
        GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => "2026-11-03 {$time}:00", 'value' => $value]);
    }

    GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => now()->subDays(8), 'value' => 9.9]);
    GaugeReading::factory()->create(['gauge_station_id' => $other->id, 'measured_at' => now(), 'value' => 1.0]);

    $points = app(GaugeLevelHistory::class)->forStations(collect([$station->id]));

    expect($points)->toHaveKeys([$station->id])
        ->and($points[$station->id])->toBe([
            [strtotime('2026-11-03 10:15:00 UTC'), 0.2],
            [strtotime('2026-11-03 11:45:00 UTC'), 0.4],
            [strtotime('2026-11-03 12:00:00 UTC'), 0.5],
        ])
        ->and(app(GaugeLevelHistory::class)->forStations(collect()))->toBe([]);
});
