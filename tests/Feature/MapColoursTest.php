<?php

use App\Actions\Ingest\RefreshRegions;
use App\Enums\OverflowStatus;
use App\Enums\SpillRecency;
use App\Models\BathingWater;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;

it('colours an overflow by its own spills on the same scale as a place, and an out of date one as no data', function () {
    expect(SpillRecency::forMonitor(OverflowStatus::Discharging, null, false))->toBe(SpillRecency::DischargingNow)
        ->and(SpillRecency::forMonitor(OverflowStatus::NotDischarging, now()->subHours(5), false))->toBe(SpillRecency::Within24Hours)
        ->and(SpillRecency::forMonitor(OverflowStatus::NotDischarging, now()->subHours(30), false))->toBe(SpillRecency::Within48Hours)
        ->and(SpillRecency::forMonitor(OverflowStatus::NotDischarging, now()->subDays(5), false))->toBe(SpillRecency::None)
        ->and(SpillRecency::forMonitor(OverflowStatus::NotDischarging, null, false))->toBe(SpillRecency::None)
        ->and(SpillRecency::forMonitor(OverflowStatus::Offline, null, false))->toBe(SpillRecency::Offline)
        ->and(SpillRecency::forMonitor(OverflowStatus::Discharging, null, true))->toBe(SpillRecency::NoData)
        ->and(SpillRecency::DischargingNow->onMap(true))->toBe('no_data')
        ->and(SpillRecency::DischargingNow->onMap())->toBe('discharging_now')
        ->and(SpillRecency::None->mapNote())->toBeNull()
        ->and(SpillRecency::None->mapNote(stale: true))->toBe('Overflow data out of date')
        ->and(SpillRecency::Within48Hours->mapNote(nearby: true))->toBe('Spill nearby in the last 48 hours')
        ->and(SpillRecency::monitorNote(OverflowStatus::NotDischarging, now()->subDays(5), now(), false))->toBeNull()
        ->and(SpillRecency::monitorNote(OverflowStatus::Discharging, null, now(), false))->toBe('Discharging now')
        ->and(SpillRecency::monitorNote(OverflowStatus::Offline, null, now(), false))->toBe('Monitor offline')
        ->and(SpillRecency::monitorNote(OverflowStatus::NotDischarging, null, now()->subDays(2), true))->toBe('Last reported 2 days ago');
});

it('marks the spot and its overflows by their spills, with a key for both shapes', function () {
    $region = Region::factory()->create(['slug' => 'yorkshire']);
    $river = River::factory()->create(['region_id' => $region->id, 'slug' => 'wharfe']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ilkley', 'slug' => 'ilkley', 'river_metres' => 50000]);
    $quiet = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 40000]);
    SpillEvent::factory()->create(['overflow_monitor_id' => $quiet->id, 'started_at' => now()->subHours(8), 'ended_at' => now()->subHours(6)]);

    $this->get(route('spots.show', [$region, $river, $spot]))
        ->assertOk()
        ->assertSee('&quot;kind&quot;:&quot;place&quot;,&quot;recency&quot;:&quot;within_24_hours&quot;', false)
        ->assertSee('&quot;kind&quot;:&quot;monitor&quot;,&quot;recency&quot;:&quot;within_24_hours&quot;', false)
        ->assertSee('&quot;note&quot;:&quot;Spill upstream in the last 24 hours&quot;', false)
        ->assertSee('&quot;note&quot;:&quot;Last spill ended 6 hours ago&quot;,&quot;link&quot;:&quot;View overflow&quot;', false)
        ->assertSeeInOrder(['aria-label="Map key"', 'Spills upstream:', 'Overflow discharging', 'Spill in last 48h', 'No data or out of date', 'Ilkley', 'Overflow'], false);

    // The overflow's own page colours it the same way, with the places it reaches shown without a status.
    app(RefreshRegions::class)->handle([$region->id]);
    $this->get(route('overflows.show', $quiet))
        ->assertOk()
        ->assertSee('&quot;kind&quot;:&quot;monitor&quot;,&quot;recency&quot;:&quot;within_24_hours&quot;', false)
        ->assertSeeInOrder(['aria-label="Map key"', 'Spills:', 'Place it reaches'], false);
});

it('colours beaches by the spills nearby rather than their classification', function () {
    $region = Region::factory()->create(['slug' => 'yorkshire']);
    $beach = BathingWater::factory()->create(['region_id' => $region->id, 'slug' => 'runswick-bay', 'latitude' => 54.28, 'longitude' => -0.40]);
    OverflowMonitor::factory()->discharging()->create(['latitude' => 54.285, 'longitude' => -0.40]);
    app(RefreshRegions::class)->handle([$region->id]);

    foreach ([route('beaches.index', $region), route('beaches.show', [$region, $beach])] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('&quot;kind&quot;:&quot;place&quot;', false)
            ->assertSee('&quot;recency&quot;:&quot;discharging_now&quot;', false)
            ->assertDontSee('&quot;classification&quot;', false)
            ->assertSee('Spills nearby:');
    }
});

it('leaves the spill colours out of the key where we do not read the overflow data', function () {
    $region = Region::factory()->create(['slug' => 'wales', 'overflow_feeds' => []]);
    $river = River::factory()->create(['region_id' => $region->id, 'slug' => 'wye']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'slug' => 'hay-on-wye']);

    $this->get(route('spots.show', [$region, $river, $spot]))
        ->assertOk()
        ->assertSee('&quot;recency&quot;:&quot;not_covered&quot;', false)
        ->assertDontSee('Spills upstream:');
});
