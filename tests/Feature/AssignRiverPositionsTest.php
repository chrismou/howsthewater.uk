<?php

use App\Actions\Rivers\AssignRiverPositions;
use App\Models\GaugeStation;
use App\Models\OverflowMonitor;
use App\Models\River;
use App\Models\Spot;

it('positions matching monitors, gauges and spots along the centreline and links the nearest gauge', function () {
    $river = River::factory()->withCentreline()->create(['name' => 'River Test', 'aliases' => ['River Test']]);

    $upstreamMonitor = OverflowMonitor::factory()->create(['receiving_watercourse' => 'River Test', 'latitude' => 53.925, 'longitude' => -1.850]);
    $downstreamMonitor = OverflowMonitor::factory()->create(['receiving_watercourse' => 'river test', 'latitude' => 53.825, 'longitude' => -1.650]);
    $otherRiverMonitor = OverflowMonitor::factory()->create(['receiving_watercourse' => 'River Other', 'latitude' => 53.9, 'longitude' => -1.8]);
    $farAwayMonitor = OverflowMonitor::factory()->create(['receiving_watercourse' => 'River Test', 'latitude' => 54.5, 'longitude' => -1.0]);

    $upstreamGauge = GaugeStation::factory()->create(['river_id' => $river->id, 'latitude' => 53.90, 'longitude' => -1.80]);
    $downstreamGauge = GaugeStation::factory()->create(['river_id' => $river->id, 'latitude' => 53.83, 'longitude' => -1.66]);

    $spot = Spot::factory()->create(['river_id' => $river->id, 'latitude' => 53.85, 'longitude' => -1.70, 'river_metres' => null]);

    $result = app(AssignRiverPositions::class)->handle($river);

    expect($result)->toBe(['monitors' => 2, 'gauges' => 2, 'spots' => 1])
        ->and($upstreamMonitor->fresh()->river_id)->toBe($river->id)
        ->and($upstreamMonitor->fresh()->river_metres)->toBeLessThan($downstreamMonitor->fresh()->river_metres)
        ->and($otherRiverMonitor->fresh()->river_id)->toBeNull()
        ->and($farAwayMonitor->fresh()->river_id)->toBeNull();

    $spot->refresh();

    expect($spot->river_metres)->toBeGreaterThan($upstreamMonitor->fresh()->river_metres)
        ->and($spot->river_metres)->toBeLessThan($downstreamMonitor->fresh()->river_metres)
        ->and($spot->gaugeStation->is($downstreamGauge))->toBeTrue();

    expect($spot->upstreamMonitors()->pluck('id')->all())->toBe([$upstreamMonitor->id]);
});

it('does nothing for a river without a centreline', function () {
    $river = River::factory()->create();

    expect(app(AssignRiverPositions::class)->handle($river))->toBe(['monitors' => 0, 'gauges' => 0, 'spots' => 0]);
});
