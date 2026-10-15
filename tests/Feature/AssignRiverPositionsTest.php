<?php

use App\Actions\Rivers\AssignRiverPositions;
use App\Models\GaugeStation;
use App\Models\OverflowMonitor;
use App\Models\River;
use App\Models\Spot;
use App\Services\Conditions\UpstreamMonitorFinder;

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

    expect(app(UpstreamMonitorFinder::class)->for($spot)->map(fn ($summary) => $summary->monitor->id)->all())->toBe([$upstreamMonitor->id]);
});

it('records where a tributary joins its parent river', function () {
    $parent = River::factory()->withCentreline()->create();
    $tributary = River::factory()->create([
        'parent_river_id' => $parent->id,
        'centreline' => [[53.950, -1.750], [53.900, -1.750], [53.851, -1.701]],
        'length_metres' => 9000,
    ]);

    app(AssignRiverPositions::class)->handle($tributary);

    // The tributary's mouth sits roughly at the parent's midpoint.
    expect($tributary->fresh()->joins_at_metres)->toBeBetween(15000, 19000);
});

it('records where a main river joins the river it flows into', function () {
    $ouse = River::factory()->withCentreline()->create();
    $ure = River::factory()->create([
        'flows_into_river_id' => $ouse->id,
        'centreline' => [[53.950, -1.750], [53.900, -1.750], [53.851, -1.701]],
        'length_metres' => 9000,
    ]);

    app(AssignRiverPositions::class)->handle($ure);

    expect($ure->fresh()->joins_at_metres)->toBeBetween(15000, 19000);
});

it('positions monitors on unnamed tributaries of the river with extra slack', function () {
    $river = River::factory()->withCentreline()->create(['name' => 'River Test', 'aliases' => ['River Test']]);

    $tributaryMonitor = OverflowMonitor::factory()->create(['receiving_watercourse' => 'Trib Of River Test', 'latitude' => 53.88, 'longitude' => -1.70]);
    $tooFarMonitor = OverflowMonitor::factory()->create(['receiving_watercourse' => 'Trib Of River Test', 'latitude' => 54.00, 'longitude' => -1.60]);

    app(AssignRiverPositions::class)->handle($river);

    expect($tributaryMonitor->fresh()->river_id)->toBe($river->id)
        ->and($tooFarMonitor->fresh()->river_id)->toBeNull();
});

it('does nothing for a river without a centreline', function () {
    $river = River::factory()->create();

    expect(app(AssignRiverPositions::class)->handle($river))->toBe(['monitors' => 0, 'gauges' => 0, 'spots' => 0]);
});

it('prefers a stage gauge over a nearer mAOD gauge when linking a spot', function () {
    $river = River::factory()->withCentreline()->create();

    $nearAodGauge = GaugeStation::factory()->aboveOrdnanceDatum()->create(['river_id' => $river->id, 'latitude' => 53.85, 'longitude' => -1.70]);
    $farStageGauge = GaugeStation::factory()->create(['river_id' => $river->id, 'latitude' => 53.90, 'longitude' => -1.80]);

    $spot = Spot::factory()->create(['river_id' => $river->id, 'latitude' => 53.85, 'longitude' => -1.70, 'river_metres' => null]);

    app(AssignRiverPositions::class)->handle($river);

    expect($spot->fresh()->gaugeStation->is($farStageGauge))->toBeTrue()
        ->and($nearAodGauge->fresh()->river_metres)->not->toBeNull();

    // Once the only stage gauge is more than 10 km along the river, the sea level gauge beside the spot wins.
    $farStageGauge->update(['latitude' => 53.95, 'longitude' => -1.90]);

    app(AssignRiverPositions::class)->handle($river);

    expect($spot->fresh()->gaugeStation->is($nearAodGauge))->toBeTrue();
});
