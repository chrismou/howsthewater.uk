<?php

use App\Enums\BathingWaterClassification;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;

it('lists every region with rivers, beaches or lakes on its own page, ahead of region slugs', function () {
    $yorkshire = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    $scotland = Region::factory()->create(['name' => 'Scotland', 'slug' => 'scotland']);
    Spot::factory()->create(['river_id' => River::factory()->create(['region_id' => $yorkshire->id, 'name' => 'River Wharfe'])->id]);
    BathingWater::factory()->create(['region_id' => $scotland->id, 'name' => 'Portobello (Central)', 'latitude' => 55.9527, 'longitude' => -3.1018, 'classification' => BathingWaterClassification::Excellent]);
    BathingWater::factory()->create(['region_id' => $scotland->id, 'name' => 'Fisherrow Sands', 'latitude' => 56.4600, 'longitude' => -2.8700, 'classification' => BathingWaterClassification::Poor]);
    $millport = BathingWater::factory()->create(['region_id' => $scotland->id, 'name' => 'Millport Bay', 'latitude' => 55.7500, 'longitude' => -4.9200, 'classification' => BathingWaterClassification::Good]);
    OverflowMonitor::factory()->discharging()->create(['latitude' => $millport->latitude, 'longitude' => $millport->longitude]);

    // The cards sit straight under the H1, so they are H2s rather than skipping a level.
    $this->get(route('hubs.rivers'))->assertOk()
        ->assertSee('<h1', false)
        ->assertSee('<h2 class="font-display text-3xl font-semibold tracking-tight">Yorkshire</h2>', false)
        ->assertDontSee('<h3', false)
        ->assertSee(route('regions.show', $yorkshire))
        ->assertDontSee(route('regions.show', $scotland));

    $this->get('/beaches')->assertOk()
        ->assertSee(route('beaches.index', $scotland))
        ->assertDontSee(route('beaches.index', $yorkshire))
        ->assertSeeInOrder(['Beaches', '3', 'Excellent or good', '2', 'Overflow discharging nearby', '1', 'Classified poor', '1']);

    $this->get(route('hubs.lakes'))->assertOk()->assertSee('Nothing here yet.');

    Lake::factory()->create(['region_id' => $yorkshire->id, 'name' => 'Semerwater']);
    $this->artisan('regions:refresh --all')->assertSuccessful();

    $this->get(route('hubs.lakes'))->assertOk()->assertSee(route('lakes.index', $yorkshire));
});

it('links the header menus to those pages, so they lead somewhere without JavaScript', function () {
    Spot::factory()->create(['river_id' => River::factory()->create(['region_id' => Region::factory()->create(['slug' => 'yorkshire'])->id])->id]);

    $this->get(route('home'))->assertOk()
        ->assertSee('href="'.route('hubs.rivers').'" data-nav-trigger', false)
        ->assertDontSee('data-nav-menu="beaches"', false);
});

it('refuses a region slug that a top-level page already takes', function (string $slug) {
    expect(fn () => Region::factory()->create(['slug' => $slug]))->toThrow(InvalidArgumentException::class);
})->with(Region::RESERVED_SLUGS);

it('gives each region an overview with a card for each kind of place it has', function () {
    $region = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    River::factory()->create(['region_id' => $region->id, 'name' => 'River Wharfe', 'slug' => 'wharfe']);
    BathingWater::factory()->create(['region_id' => $region->id]);

    $this->get(route('regions.show', $region))
        ->assertOk()
        ->assertSee('<title>Swimming in Yorkshire: rivers and beaches', false)
        ->assertSee('href="'.route('rivers.index', $region).'"', false)
        ->assertSee('href="'.route('beaches.index', $region).'"', false)
        ->assertDontSee('href="'.route('lakes.index', $region).'"', false)
        ->assertSee('View Yorkshire rivers');

    $this->get(route('rivers.index', $region))->assertOk()->assertSee('Yorkshire rivers')->assertSee('/yorkshire/rivers/wharfe');
    $this->get(route('lakes.index', $region))->assertNotFound();
});
