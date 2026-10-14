<?php

use App\Enums\FloodSeverity;
use App\Models\FloodArea;
use App\Models\FloodWarning;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;

/**
 * @return array{0: Region, 1: River, 2: Spot}
 */
function feedSpot(): array
{
    $region = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Wharfe', 'slug' => 'wharfe']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Otley', 'slug' => 'otley', 'river_metres' => 50000]);

    return [$region, $river, $spot];
}

it('lists upstream overflows starting and stopping in the spot feed', function () {
    [$region, $river, $spot] = feedSpot();
    $monitor = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 47600, 'external_id' => 'YWS00177']);
    $finished = SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subHours(5), 'ended_at' => now()->subHours(3)->subMinutes(50)]);
    $ongoing = SpillEvent::factory()->ongoing()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subMinutes(20)]);

    $this->get(route('spots.feed', [$region, $river, $spot]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
        ->assertSee('<title>Otley, River Wharfe - '.e(config('app.name')).'</title>', false)
        ->assertSee('Not a statement that the water is safe or clean.')
        ->assertSeeInOrder([
            "spill-{$ongoing->id}-start",
            "spill-{$finished->id}-end",
            "spill-{$finished->id}-start",
        ])
        ->assertSee('Storm overflow YWS00177 started discharging, 2.4 km upstream on the River Wharfe')
        ->assertSee('Storm overflow YWS00177 stopped discharging after 1 h 10 min')
        ->assertDontSee("spill-{$ongoing->id}-end");
});

it('gives long spills in days and hours', function () {
    [$region, $river, $spot] = feedSpot();
    $monitor = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 40000, 'external_id' => 'YWS00186']);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subDays(8), 'ended_at' => now()->subDays(8)->addMinutes(167 * 60 + 20)]);

    $this->get(route('spots.feed', [$region, $river, $spot]))
        ->assertOk()
        ->assertSee('Storm overflow YWS00186 stopped discharging after 6 days 23 h');
});

it('includes flood warnings in force in the spot feed', function () {
    [$region, $river, $spot] = feedSpot();
    $area = FloodArea::factory()->hasAttached($spot)->create(['code' => '122WAF943', 'label' => 'Lower River Wharfe']);
    FloodWarning::factory()->severity(FloodSeverity::FloodWarning)->create(['flood_area_id' => $area->id]);

    $this->get(route('spots.feed', [$region, $river, $spot]))
        ->assertOk()
        ->assertSee('Flood warning in force: Lower River Wharfe')
        ->assertSee('https://check-for-flooding.service.gov.uk/target-area/122WAF943');
});

it('covers the river and its tributaries in the river feed', function () {
    [$region, $river] = feedSpot();
    $tributary = River::factory()->create(['region_id' => $region->id, 'parent_river_id' => $river->id, 'name' => 'River Washburn']);
    $elsewhere = River::factory()->create(['region_id' => $region->id]);

    foreach ([[$river, 'YWS00001'], [$tributary, 'YWS00002'], [$elsewhere, 'YWS00003']] as [$on, $id]) {
        $monitor = OverflowMonitor::factory()->create(['river_id' => $on->id, 'external_id' => $id]);
        SpillEvent::factory()->ongoing()->create(['overflow_monitor_id' => $monitor->id]);
    }

    $this->get(route('rivers.feed', [$region, $river]))
        ->assertOk()
        ->assertSee('YWS00001 started discharging on the River Wharfe')
        ->assertSee('YWS00002 started discharging on the River Washburn')
        ->assertDontSee('YWS00003');
});

it('links each page to its feed for readers to discover', function () {
    [$region, $river, $spot] = feedSpot();

    $this->get(route('spots.show', [$region, $river, $spot]))
        ->assertOk()
        ->assertSee('<link rel="alternate" type="application/rss+xml"', false)
        ->assertSee(route('spots.feed', [$region, $river, $spot]));

    $this->get(route('rivers.show', [$region, $river]))
        ->assertOk()
        ->assertSee(route('rivers.feed', [$region, $river]))
        ->assertSee('Follow the Wharfe by RSS');
});

it('has no feeds for unpublished spots or tributaries', function () {
    [$region, $river] = feedSpot();
    $hidden = Spot::factory()->unpublished()->create(['river_id' => $river->id, 'slug' => 'hidden']);
    $tributary = River::factory()->create(['region_id' => $region->id, 'parent_river_id' => $river->id, 'slug' => 'washburn']);

    $this->get(route('spots.feed', [$region, $river, $hidden]))->assertNotFound();
    $this->get(route('rivers.feed', [$region, $tributary]))->assertNotFound();
});

it('keeps the feed valid XML whatever the EA message contains', function () {
    [$region, $river, $spot] = feedSpot();
    $area = FloodArea::factory()->hasAttached($spot)->create(['label' => 'Wharfe & Washburn']);
    FloodWarning::factory()->create(['flood_area_id' => $area->id, 'message' => "Levels rising\x0B fast <now>"]);

    $xml = $this->get(route('spots.feed', [$region, $river, $spot]))->assertOk()->getContent();

    expect(simplexml_load_string($xml))->not->toBeFalse()
        ->and($xml)->toContain('Wharfe &amp; Washburn')
        ->and($xml)->not->toContain("\x0B");
});
