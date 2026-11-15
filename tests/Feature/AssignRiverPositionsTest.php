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

it('links no gauge to a spot more than 25 km along the river from every gauge', function () {
    $river = River::factory()->withCentreline()->create();
    GaugeStation::factory()->create(['river_id' => $river->id, 'latitude' => 53.950, 'longitude' => -1.900]);
    $nearSpot = Spot::factory()->create(['river_id' => $river->id, 'latitude' => 53.900, 'longitude' => -1.800]);
    $farSpot = Spot::factory()->create(['river_id' => $river->id, 'latitude' => 53.800, 'longitude' => -1.600]);

    app(AssignRiverPositions::class)->handle($river);

    expect($nearSpot->fresh()->gauge_station_id)->not->toBeNull()
        ->and($farSpot->fresh()->river_metres)->toBeGreaterThan(25000)
        ->and($farSpot->fresh()->gauge_station_id)->toBeNull();
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

it('moves an overflow named like its parent to a branch whose line it sits nearer, and leaves the parent its own', function () {
    $parent = River::factory()->withCentreline()->create(['name' => 'River Test', 'aliases' => ['River Test']]);
    // A branch the company writes as the parent ("RIVER NENE" on the Nene's Brampton Branch).
    $branch = River::factory()->create([
        'name' => 'Test Brampton Branch',
        'aliases' => ['Test Brampton Branch', 'River Test'],
        'parent_river_id' => $parent->id,
        'centreline' => [[53.950, -1.750], [53.900, -1.750], [53.851, -1.701]],
        'length_metres' => 9000,
    ]);
    $onBranch = OverflowMonitor::factory()->create(['receiving_watercourse' => 'RIVER TEST', 'latitude' => 53.925, 'longitude' => -1.751, 'river_id' => $parent->id, 'river_metres' => 12000]);
    $onParent = OverflowMonitor::factory()->create(['receiving_watercourse' => 'RIVER TEST', 'latitude' => 53.855, 'longitude' => -1.710, 'river_id' => $parent->id, 'river_metres' => 28000]);
    $otherName = OverflowMonitor::factory()->create(['receiving_watercourse' => 'RIVER OTHER', 'latitude' => 53.925, 'longitude' => -1.751, 'river_id' => $parent->id, 'river_metres' => 12000]);
    $unplaced = OverflowMonitor::factory()->create(['receiving_watercourse' => 'TEST BRAMPTON BRANCH', 'latitude' => 53.940, 'longitude' => -1.752]);

    app(AssignRiverPositions::class)->handle($branch);

    expect($onBranch->fresh()->river_id)->toBe($branch->id)
        ->and($onBranch->fresh()->river_metres)->toBeBetween(2000, 4000)
        ->and($onParent->fresh()->river_id)->toBe($parent->id)
        ->and($onParent->fresh()->river_metres)->toBe(28000)
        ->and($otherName->fresh()->river_id)->toBe($parent->id)
        ->and($unplaced->fresh()->river_id)->toBe($branch->id);
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

it('positions monitors that name the river bare, as Southern Water writes them', function () {
    $river = River::factory()->withCentreline()->create(['name' => 'River Test', 'aliases' => ['River Test']]);
    $bare = OverflowMonitor::factory()->create(['receiving_watercourse' => 'TEST', 'latitude' => 53.925, 'longitude' => -1.850]);
    $other = OverflowMonitor::factory()->create(['receiving_watercourse' => 'OTHER', 'latitude' => 53.925, 'longitude' => -1.850]);
    $tributary = OverflowMonitor::factory()->create(['receiving_watercourse' => 'TRIBUTARY OF THE TEST', 'latitude' => 53.925, 'longitude' => -1.850]);

    app(AssignRiverPositions::class)->handle($river);

    expect($bare->fresh()->river_id)->toBe($river->id)
        ->and($tributary->fresh()->river_id)->toBe($river->id)
        ->and($other->fresh()->river_id)->toBeNull();

    // A river of the same name elsewhere never takes it, as it is beyond the offset from that river's line.
    $elsewhere = River::factory()->create(['name' => 'River Test', 'aliases' => ['River Test'], 'centreline' => [[51.00, -1.00], [51.10, -1.10]]]);
    $farBare = OverflowMonitor::factory()->create(['receiving_watercourse' => 'TEST', 'latitude' => 53.9, 'longitude' => -1.8]);

    app(AssignRiverPositions::class)->handle($elsewhere);

    expect($farBare->fresh()->river_id)->toBeNull()
        ->and($bare->fresh()->river_id)->toBe($river->id);
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
