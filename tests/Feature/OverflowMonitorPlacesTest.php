<?php

use App\Actions\Ingest\RefreshRegions;
use App\Models\BathingWater;
use App\Models\OverflowMonitor;
use App\Models\OverflowMonitorPlace;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;

it('links each spot to the overflows upstream and each beach to those nearby, as their pages show them', function () {
    $region = Region::factory()->create();
    $river = River::factory()->create(['region_id' => $region->id]);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'river_metres' => 50000]);
    $upstream = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 45000]);
    $downstream = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 55000]);
    $beach = BathingWater::factory()->create(['region_id' => $region->id, 'latitude' => 54.28, 'longitude' => -0.40]);
    $nearBeach = OverflowMonitor::factory()->create(['latitude' => 54.285, 'longitude' => -0.40]);

    app(RefreshRegions::class)->handle([$region->id]);

    expect($upstream->places()->sole())
        ->place_type->toBe(Spot::class)
        ->place_id->toBe($spot->id)
        ->distance_metres->toBe(5000)
        ->and($downstream->places()->exists())->toBeFalse()
        ->and($nearBeach->places()->sole()->place_type)->toBe(BathingWater::class)
        ->and($nearBeach->places()->sole()->distance_metres)->toBeBetween(500, 600);

    // Moved below the spot, it is no longer upstream; an unpublished spot loses its links.
    $upstream->update(['river_metres' => 60000]);
    $downstream->update(['river_metres' => 40000]);
    app(RefreshRegions::class)->handle([$region->id]);

    expect($upstream->places()->exists())->toBeFalse()
        ->and($downstream->places()->sole()->distance_metres)->toBe(10000);

    $spot->update(['is_published' => false]);
    app(RefreshRegions::class)->handle([$region->id]);

    expect($downstream->places()->exists())->toBeFalse();
});

it('links nothing where we do not read the overflow feed', function () {
    $wales = Region::factory()->create(['name' => 'Wales', 'slug' => 'wales', 'overflow_feeds' => []]);
    $river = River::factory()->create(['region_id' => $wales->id]);
    Spot::factory()->create(['river_id' => $river->id, 'river_metres' => 50000]);
    OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 45000]);

    app(RefreshRegions::class)->handle([$wales->id]);

    expect(OverflowMonitorPlace::query()->count())->toBe(0);
});
