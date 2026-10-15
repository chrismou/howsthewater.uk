<?php

use App\Models\OverflowMonitor;
use App\Models\River;
use App\Models\Spot;
use App\Services\Conditions\UpstreamMonitorFinder;

it('includes monitors on tributaries that join above the spot, with flow distances', function () {
    $river = River::factory()->create(['length_metres' => 100000]);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'river_metres' => 50000]);

    $mainUpstream = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 45000]);
    OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 55000]);

    $joinsAbove = River::factory()->tributaryOf($river, 40000)->create(['length_metres' => 8000]);
    $tributaryMonitor = OverflowMonitor::factory()->create(['river_id' => $joinsAbove->id, 'river_metres' => 6000]);

    $joinsBelow = River::factory()->tributaryOf($river, 60000)->create(['length_metres' => 5000]);
    OverflowMonitor::factory()->create(['river_id' => $joinsBelow->id, 'river_metres' => 1000]);

    $subTributary = River::factory()->tributaryOf($joinsAbove, 3000)->create(['length_metres' => 2000]);
    $subTributaryMonitor = OverflowMonitor::factory()->create(['river_id' => $subTributary->id, 'river_metres' => 500]);

    $summaries = app(UpstreamMonitorFinder::class)->for($spot);

    expect($summaries->map(fn ($summary) => $summary->monitor->id)->all())
        ->toBe([$mainUpstream->id, $tributaryMonitor->id, $subTributaryMonitor->id]);

    // Tributary monitor: 2km to the tributary mouth, then 10km down the main river.
    expect($summaries[1]->distanceUpstreamMetres)->toBe(12000)
        ->and($summaries[1]->river->is($joinsAbove))->toBeTrue();

    // Sub-tributary: 1.5km to its mouth, 5km down the tributary, 10km down the main river.
    expect($summaries[2]->distanceUpstreamMetres)->toBe(16500);
});

it('includes monitors on a main river that joins above the spot, and its tributaries', function () {
    $ouse = River::factory()->create(['length_metres' => 100000]);
    $york = Spot::factory()->create(['river_id' => $ouse->id, 'river_metres' => 30000]);

    $ure = River::factory()->create(['flows_into_river_id' => $ouse->id, 'joins_at_metres' => 0, 'length_metres' => 120000]);
    $ureMonitor = OverflowMonitor::factory()->create(['river_id' => $ure->id, 'river_metres' => 110000]);
    $swale = River::factory()->create(['flows_into_river_id' => $ure->id, 'joins_at_metres' => 115000, 'length_metres' => 90000]);
    $swaleMonitor = OverflowMonitor::factory()->create(['river_id' => $swale->id, 'river_metres' => 85000]);

    $wharfe = River::factory()->create(['flows_into_river_id' => $ouse->id, 'joins_at_metres' => 60000, 'length_metres' => 100000]);
    OverflowMonitor::factory()->create(['river_id' => $wharfe->id, 'river_metres' => 90000]);

    $summaries = app(UpstreamMonitorFinder::class)->for($york);

    expect($summaries->map(fn ($summary) => $summary->monitor->id)->all())->toBe([$ureMonitor->id, $swaleMonitor->id])
        // 10km down the Ure, then 30km down the Ouse.
        ->and($summaries[0]->distanceUpstreamMetres)->toBe(40000)
        // 5km down the Swale, 5km down the Ure, then 30km down the Ouse.
        ->and($summaries[1]->distanceUpstreamMetres)->toBe(40000);

    expect($ure->isTributary())->toBeFalse();
});

it('returns nothing for an unpositioned spot', function () {
    $spot = Spot::factory()->create(['river_metres' => null]);

    expect(app(UpstreamMonitorFinder::class)->for($spot))->toBeEmpty();
});
