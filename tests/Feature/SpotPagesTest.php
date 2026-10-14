<?php

use App\Models\GaugeReading;
use App\Models\GaugeStation;
use App\Models\OverflowMonitor;
use App\Models\RainfallReading;
use App\Models\RainfallStation;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use App\Services\Conditions\ConditionsCache;
use App\Services\Conditions\RegionSummaries;
use App\View\Components\Help;

/**
 * @return array{0: Region, 1: River, 2: Spot}
 */
function spotWithUrl(array $spotAttributes = [], array $riverAttributes = []): array
{
    $region = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    $river = River::factory()->create(['region_id' => $region->id, ...$riverAttributes]);
    $spot = Spot::factory()->create(['river_id' => $river->id, ...$spotAttributes]);

    return [$region, $river, $spot];
}

it('introduces the site and its regions on the home page', function () {
    [$region, $river, $spot] = spotWithUrl(['name' => 'Otley', 'slug' => 'otley'], ['name' => 'River Wharfe', 'slug' => 'wharfe']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee("What's the river doing right now?", false)
        ->assertSee('How to read it')
        ->assertSee('What this site does not do')
        ->assertSee('Open Yorkshire')
        ->assertSee('/yorkshire/wharfe/otley')
        ->assertSee('/yorkshire"', false);
});

it('shows a river page with its spots source to mouth', function () {
    [$region, $river] = spotWithUrl(['name' => 'Castleford', 'river_metres' => 90000], ['slug' => 'aire']);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Skipton', 'river_metres' => 20000]);

    $this->get('/yorkshire/aire')
        ->assertOk()
        ->assertSeeInOrder(['Skipton', 'Castleford']);
});

it('scopes rivers and spots to their region', function () {
    [$region, $river, $spot] = spotWithUrl(['slug' => 'otley'], ['slug' => 'calder']);
    $other = Region::factory()->create(['slug' => 'lancashire']);
    River::factory()->create(['region_id' => $other->id, 'slug' => 'calder', 'name' => 'River Calder']);

    $this->get('/yorkshire/calder')->assertOk();
    $this->get('/lancashire/calder')->assertOk()->assertDontSee($spot->name);
    $this->get('/lancashire/calder/otley')->assertNotFound();
    $this->get('/yorkshire/wharfe')->assertNotFound();
});

it('shows a spot page with upstream monitors and the spill summary', function () {
    [$region, $river, $spot] = spotWithUrl(['river_metres' => 50000]);
    $monitor = OverflowMonitor::factory()->discharging()->create(['river_id' => $river->id, 'river_metres' => 45000]);

    $this->get(route('spots.show', [$region, $river, $spot]))
        ->assertOk()
        ->assertSee($spot->name)
        ->assertSee('Discharging upstream now')
        ->assertSee($monitor->external_id);
});

it('hides unpublished spots', function () {
    [$region, $river, $spot] = spotWithUrl(['is_published' => false]);

    $this->get(route('spots.show', [$region, $river, $spot]))->assertNotFound();
    $this->get(route('home'))->assertDontSee($spot->name);
});

it('lists rivers by catchment in the region menu', function () {
    [$region, $river] = spotWithUrl([], ['name' => 'River Wharfe', 'catchment' => 'Yorkshire Ouse']);
    River::factory()->create(['region_id' => $region->id, 'name' => 'River Esk', 'catchment' => 'Esk and Coast']);
    River::factory()->tributaryOf($river, 1000)->create(['region_id' => $region->id, 'name' => 'Oak Beck']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Yorkshire', 'Yorkshire Ouse', 'River Wharfe', 'Esk and Coast', 'River Esk'])
        ->assertDontSee('Oak Beck');
});

it('finds spots and rivers by name', function () {
    [$region, $river, $spot] = spotWithUrl(['name' => 'Otley', 'slug' => 'otley'], ['name' => 'River Wharfe', 'slug' => 'wharfe']);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ilkley']);

    $this->get(route('search', ['q' => 'otl']))
        ->assertOk()
        ->assertSee('Otley')
        ->assertSee('/yorkshire/wharfe/otley')
        ->assertDontSee('Ilkley');

    $this->get(route('search', ['q' => 'wharfe']))
        ->assertOk()
        ->assertSee('River Wharfe')
        ->assertSee('/yorkshire/wharfe"', false);

    $this->get(route('search', ['q' => 'zzz']))->assertOk()->assertSee('Nothing matched');
});

it('warns when monitor and gauge data are out of date', function () {
    [$region, $river, $spot] = spotWithUrl(['river_metres' => 50000]);
    $station = GaugeStation::factory()->create(['river_id' => $river->id]);
    $spot->update(['gauge_station_id' => $station->id]);
    OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 45000, 'source_updated_at' => now()->subHours(8)]);
    GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => now()->subHours(8)]);

    $this->get(route('spots.show', [$region, $river, $spot]))
        ->assertOk()
        ->assertSee('Monitor data is out of date')
        ->assertSee('No reading from this gauge for 8 hours');

    $this->get(route('rivers.show', [$region, $river]))
        ->assertOk()
        ->assertSee('Monitor data is 8 hours old');
});

it('does not warn when data is fresh', function () {
    [$region, $river, $spot] = spotWithUrl(['river_metres' => 50000]);
    OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 45000]);

    $this->get(route('spots.show', [$region, $river, $spot]))
        ->assertOk()
        ->assertDontSee('Monitor data is out of date');
});

it('shows rainfall at the nearest rain gauge', function () {
    $station = RainfallStation::factory()->create();
    [$region, $river, $spot] = spotWithUrl(['rainfall_station_id' => $station->id, 'river_metres' => null]);
    RainfallReading::factory()->create(['rainfall_station_id' => $station->id, 'measured_at' => now()->subMinutes(15), 'millimetres' => 3.25]);

    $this->get(route('spots.show', [$region, $river, $spot]))
        ->assertOk()
        ->assertSee('in the last 24 hours')
        ->assertSee('3.3 mm');

    $this->get(route('rivers.show', [$region, $river]))->assertSee('3.3 mm rain in 24h');
});

it('treats search wildcards literally and caps the query length', function () {
    [$region, $river] = spotWithUrl(['name' => 'Otley']);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ilkley']);

    $this->get(route('search', ['q' => '%']))->assertOk()->assertSee('Nothing matched');
    $this->get(route('search', ['q' => '_tley']))->assertOk()->assertSee('Nothing matched');
    $this->get(route('search', ['q' => str_repeat('a', 500)]))->assertOk()->assertSee('Nothing matched');
});

it('offers a light and dark theme toggle that defaults to light', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-theme-toggle', false)
        ->assertSee('<html lang="en-GB" class="h-full">', false);
});

it('explains the data with help tooltips on the spot page', function () {
    [$region, $river, $spot] = spotWithUrl(['river_metres' => 50000]);
    OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 45000]);

    $this->get(route('spots.show', [$region, $river, $spot]))
        ->assertOk()
        ->assertSee('What does discharging now mean?')
        ->assertSee('Which monitors count as upstream?')
        ->assertSee('role="tooltip"', false);
});

it('rejects unknown glossary terms', function () {
    expect(fn () => new Help('nonsense'))->toThrow(InvalidArgumentException::class);
});

it('lists active upstream monitors with duration and spill counts, and tucks quiet ones away', function () {
    [$region, $river, $spot] = spotWithUrl(['river_metres' => 50000]);
    $active = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 45000]);
    $quiet = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 40000]);
    SpillEvent::factory()->create(['overflow_monitor_id' => $active->id, 'started_at' => now()->subDays(2), 'ended_at' => now()->subDays(2)->addMinutes(45)]);

    $this->get(route('spots.show', [$region, $river, $spot]))
        ->assertOk()
        ->assertSeeInOrder([$active->external_id, '45 min', $quiet->external_id])
        ->assertSee('1 more monitor upstream had no spill in the last 30 days')
        ->assertSee('data-quiet-monitors hidden', false)
        ->assertSee('Copy link')
        ->assertSee('openstreetmap.org/?mlat=');
});

it('shows the region page with river strips, catchment tables and the right now panel', function () {
    [$region, $river, $spot] = spotWithUrl(['name' => 'Otley', 'slug' => 'otley', 'river_metres' => 50000], ['name' => 'River Wharfe', 'slug' => 'wharfe', 'catchment' => 'Yorkshire Ouse', 'length_metres' => 100000]);
    $monitor = OverflowMonitor::factory()->discharging()->create(['river_id' => $river->id, 'river_metres' => 45000]);
    SpillEvent::factory()->ongoing()->create(['overflow_monitor_id' => $monitor->id]);

    $this->get(route('regions.show', $region))
        ->assertOk()
        ->assertSee('Every river, source to mouth')
        ->assertSee('Yorkshire Ouse')
        ->assertSee('Right now')
        ->assertSeeInOrder(['Discharging now', 'Otley'])
        ->assertSee('/yorkshire/wharfe/otley')
        ->assertSee('data-map-centrelines', false);
});

it('shows the live bar for the region on every page', function () {
    [$region, $river, $spot] = spotWithUrl(['river_metres' => 50000]);
    $monitor = OverflowMonitor::factory()->discharging()->create(['river_id' => $river->id, 'river_metres' => 45000]);
    SpillEvent::factory()->ongoing()->create(['overflow_monitor_id' => $monitor->id]);

    $this->get(route('home'))->assertOk()->assertSee('data-live-bar', false)->assertSee('>1</span> overflow discharging now', false);
    $this->get(route('spots.show', [$region, $river, $spot]))->assertOk()->assertSee('data-live-bar', false);
    $this->get(route('search', ['q' => 'x']))->assertOk()->assertSee('data-live-bar', false);
});

it('404s an unknown region', function () {
    $this->get('/narnia')->assertNotFound();
});

it('suggests featured spots on the home page before falling back to the best monitored', function () {
    [$region, $river, $busy] = spotWithUrl(['name' => 'Castleford', 'slug' => 'castleford', 'river_metres' => 90000]);
    OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 80000]);
    $popular = Spot::factory()->featured()->create(['river_id' => $river->id, 'name' => 'Ilkley', 'slug' => 'ilkley', 'river_metres' => 50000]);

    $this->get(route('home'))->assertOk()->assertSee('Try Ilkley')->assertDontSee('Try Castleford');

    $popular->update(['is_featured' => false]);
    app(ConditionsCache::class)->flush();
    app(RegionSummaries::class)->refresh($region);

    $this->get(route('home'))->assertOk()->assertSee('Try Castleford');
});
