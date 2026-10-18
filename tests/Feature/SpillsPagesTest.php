<?php

use App\Actions\Ingest\RefreshRegions;
use App\Models\BathingWater;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;

/**
 * Yorkshire with a spot on the Wharfe below one discharging overflow and one that spilled this morning.
 *
 * @return array{0: Region, 1: Spot, 2: OverflowMonitor, 3: OverflowMonitor}
 */
function spillingRegion(): array
{
    $region = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Wharfe', 'slug' => 'wharfe']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ilkley', 'slug' => 'ilkley', 'river_metres' => 50000]);
    $discharging = OverflowMonitor::factory()->discharging()->create(['external_id' => 'YWS00100', 'return_name' => 'Addingham CSO', 'river_id' => $river->id, 'river_metres' => 45000]);
    SpillEvent::factory()->ongoing()->create(['overflow_monitor_id' => $discharging->id, 'started_at' => now()->subHours(2)]);
    $spilled = OverflowMonitor::factory()->create(['external_id' => 'YWS00200', 'river_id' => $river->id, 'river_metres' => 40000]);
    SpillEvent::factory()->create(['overflow_monitor_id' => $spilled->id, 'started_at' => now()->subHours(6), 'ended_at' => now()->subHours(5)]);
    SpillEvent::factory()->create(['overflow_monitor_id' => $spilled->id, 'started_at' => now()->subDays(3), 'ended_at' => now()->subDays(3)->addHour()]);

    app(RefreshRegions::class)->handle([$region->id]);

    return [$region, $spot, $discharging, $spilled];
}

it('lists what the live bar counts: overflows discharging now, spots with a spill upstream and the last day\'s spills', function () {
    [$region, , $discharging, $spilled] = spillingRegion();

    $this->get(route('spills.region', $region))
        ->assertOk()
        ->assertSee('<title>Storm overflow spills in Yorkshire now - How\'s the Water?</title>', false)
        ->assertSeeInOrder(['id="discharging"', 'Addingham CSO', 'YWS00100', 'Ilkley', '5.0 km', 'id="spots"', 'Ilkley', 'id="recent"', 'Addingham CSO', 'ongoing', 'YWS00200'], false)
        ->assertSee(route('overflows.show', $discharging), false)
        ->assertSee(route('overflows.show', $spilled), false);

    // The live bar's counts open these lists.
    $this->get(route('regions.show', $region))
        ->assertSee(route('spills.region', $region).'#discharging', false)
        ->assertSee(route('spills.region', $region).'#spots', false);
    $this->get(route('home'))->assertSee(route('spills.index').'#discharging', false);
    $this->get(route('spills.index'))->assertOk()->assertSee('Addingham CSO')->assertSee(route('spills.region', $region), false);
});

it('lists overflows discharging near beaches apart from those on rivers', function () {
    $region = Region::factory()->create(['slug' => 'yorkshire']);
    BathingWater::factory()->create(['region_id' => $region->id, 'name' => 'Runswick Bay', 'latitude' => 54.28, 'longitude' => -0.40]);
    OverflowMonitor::factory()->discharging()->create(['return_name' => 'Runswick CSO', 'latitude' => 54.285, 'longitude' => -0.40]);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('spills.region', $region))
        ->assertOk()
        ->assertSeeInOrder(['Discharging now', 'is reporting a discharge right now', 'Discharging near beaches and lakes', 'Runswick CSO', 'Runswick Bay']);
});

it('has no spills page where we do not read the overflow feed, and never counts overflows recorded there', function () {
    [$yorkshire] = spillingRegion();
    $wales = Region::factory()->create(['name' => 'Wales', 'slug' => 'wales', 'overflow_feeds' => []]);
    $taff = River::factory()->create(['region_id' => $wales->id, 'name' => 'River Taff', 'slug' => 'taff']);
    Spot::factory()->create(['river_id' => $taff->id, 'river_metres' => 50000]);
    // A company recorded ahead of its region's feed being read, so its overflows sit on that region's rivers.
    OverflowMonitor::factory()->discharging()->create(['external_id' => 'DCWW1003', 'company' => 'Dŵr Cymru Welsh Water', 'river_id' => $taff->id, 'river_metres' => 40000]);
    app(RefreshRegions::class)->handle([$yorkshire->id, $wales->id]);

    $this->get(route('spills.region', $wales))->assertNotFound();

    // The home page's bar counts Yorkshire's one discharging overflow, and the combined list shows just that one.
    $this->get(route('home'))->assertSeeInOrder(['data-live-bar', '>1</span> <span class="hidden sm:inline">overflow</span> discharging'], false);
    $this->get(route('spills.index'))
        ->assertOk()
        ->assertSee('Addingham CSO')
        ->assertDontSee('DCWW1003')
        ->assertDontSee('England, Scotland and Wales')
        ->assertSee('<title>Storm overflow spills now, region by region - How\'s the Water?</title>', false);
});

it('warns that the lists are out of date when no overflow has reported for hours', function () {
    [$region] = spillingRegion();
    OverflowMonitor::query()->update(['source_updated_at' => now()->subHours(9)]);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('spills.region', $region))
        ->assertOk()
        ->assertSee('Monitor data is out of date.')
        ->assertSee('open at last report')
        ->assertDontSee('>ongoing<', false);
});

it('keeps spills and overflows out of region slugs', function (string $slug) {
    Region::factory()->create(['slug' => $slug]);
})->with(['spills', 'overflows'])->throws(InvalidArgumentException::class);
