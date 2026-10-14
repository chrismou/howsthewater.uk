<?php

use App\Models\GaugeReading;
use App\Models\GaugeStation;
use App\Models\OverflowMonitor;
use App\Models\RainfallReading;
use App\Models\RainfallStation;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;

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

it('lists rivers and spots on the home page', function () {
    [$region, $river, $spot] = spotWithUrl(['name' => 'Otley', 'slug' => 'otley'], ['name' => 'River Wharfe', 'slug' => 'wharfe']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('River Wharfe')
        ->assertSee('Otley')
        ->assertSee('/yorkshire/wharfe/otley');
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

    $this->get(route('home'))
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

    $this->get(route('home'))->assertSee('3.3 mm rain in 24h');
});
