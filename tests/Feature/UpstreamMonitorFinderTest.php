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

it('returns nothing for an unpositioned spot', function () {
    $spot = Spot::factory()->create(['river_metres' => null]);

    expect(app(UpstreamMonitorFinder::class)->for($spot))->toBeEmpty();
});
