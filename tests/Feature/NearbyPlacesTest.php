<?php

use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function nearbyRegion(): Region
{
    return Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
}

/**
 * The page's Nearby section alone, since the header's menu data names every beach and lake.
 */
function nearbySection(TestResponse $response): string
{
    return Str::between($response->getContent(), '>Nearby</h2>', '</section>');
}

it('links the spots above and below a spot by distance along the river', function () {
    $region = nearbyRegion();
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Wharfe', 'slug' => 'wharfe']);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Burnsall', 'slug' => 'burnsall', 'river_metres' => 20000]);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ilkley', 'slug' => 'ilkley', 'river_metres' => 29400]);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Otley', 'slug' => 'otley', 'river_metres' => 40600]);

    $this->get('/yorkshire/rivers/wharfe/ilkley')
        ->assertOk()
        ->assertSeeInOrder(['Nearby', 'Upstream', 'Burnsall', '9.4 km along the river', 'Downstream', 'Otley', '11.2 km along the river'])
        ->assertSee('All 3 spots on the River Wharfe')
        ->assertSee('Rivers in Yorkshire')
        ->assertSee(route('rivers.index', $region));

    $response = $this->get('/yorkshire/rivers/wharfe/burnsall')
        ->assertOk()
        ->assertSeeInOrder(['Nearby', 'Downstream', 'Ilkley']);

    expect(nearbySection($response))->not->toContain('Upstream')->not->toContain('Otley');
});

it('follows a main river into the river it flows into, and back up it', function () {
    $region = nearbyRegion();
    $ouse = River::factory()->create(['region_id' => $region->id, 'name' => 'River Ouse', 'slug' => 'ouse', 'length_metres' => 100000]);
    $ure = River::factory()->create(['region_id' => $region->id, 'name' => 'River Ure', 'slug' => 'ure', 'length_metres' => 110000, 'flows_into_river_id' => $ouse->id, 'joins_at_metres' => 0]);
    Spot::factory()->create(['river_id' => $ure->id, 'name' => 'Ripon', 'slug' => 'ripon', 'river_metres' => 80000]);
    Spot::factory()->create(['river_id' => $ure->id, 'name' => 'Aldwark Bridge', 'slug' => 'aldwark-bridge', 'river_metres' => 104000]);
    Spot::factory()->create(['river_id' => $ouse->id, 'name' => 'Newton-on-Ouse', 'slug' => 'newton-on-ouse', 'river_metres' => 2000]);
    Spot::factory()->create(['river_id' => $ouse->id, 'name' => 'York', 'slug' => 'york', 'river_metres' => 20000]);

    $this->get('/yorkshire/rivers/ure/aldwark-bridge')
        ->assertOk()
        ->assertSeeInOrder(['Upstream', 'Ripon', 'Downstream on the River Ouse', 'Newton-on-Ouse', '8.0 km along the river']);

    $this->get('/yorkshire/rivers/ouse/newton-on-ouse')
        ->assertOk()
        ->assertSeeInOrder(['Upstream on the River Ure', 'Aldwark Bridge', '8.0 km along the river', 'Downstream', 'York']);

    // York has a spot on its own river between it and the join, so it does not reach up the Ure.
    $response = $this->get('/yorkshire/rivers/ouse/york')->assertOk();

    expect(nearbySection($response))->toContain('Newton-on-Ouse')->not->toContain('River Ure');
});

it('lists the nearest beaches and lakes within 20 km of a spot', function () {
    $region = nearbyRegion();
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Esk', 'slug' => 'esk']);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ruswarp', 'slug' => 'ruswarp', 'latitude' => 54.0, 'longitude' => -1.0]);
    BathingWater::factory()->create(['region_id' => $region->id, 'name' => 'Whitby', 'slug' => 'whitby', 'latitude' => 54.05, 'longitude' => -1.0]);
    Lake::factory()->create(['region_id' => $region->id, 'name' => 'Scaling Dam', 'slug' => 'scaling-dam', 'latitude' => 54.1, 'longitude' => -1.0]);
    BathingWater::factory()->create(['region_id' => $region->id, 'name' => 'Scarborough South Bay', 'slug' => 'scarborough-south-bay', 'latitude' => 54.3, 'longitude' => -1.0]);

    $response = $this->get('/yorkshire/rivers/esk/ruswarp')
        ->assertOk()
        ->assertSeeInOrder(['Nearby', 'Beach', 'Whitby', '5.6 km away', 'Lake', 'Scaling Dam', '11.1 km away']);

    expect(nearbySection($response))->not->toContain('Scarborough South Bay');
});

it('says a nearby beach\'s overflow data is out of date rather than showing an old "no spills"', function () {
    $region = nearbyRegion();
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Esk', 'slug' => 'esk']);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ruswarp', 'slug' => 'ruswarp', 'latitude' => 54.0, 'longitude' => -1.0]);
    BathingWater::factory()->create(['region_id' => $region->id, 'name' => 'Whitby', 'slug' => 'whitby', 'latitude' => 54.05, 'longitude' => -1.0]);
    OverflowMonitor::factory()->create(['river_id' => null, 'latitude' => 54.051, 'longitude' => -1.0, 'source_updated_at' => now()->subDays(3)]);

    $section = nearbySection($this->get('/yorkshire/rivers/esk/ruswarp')->assertOk());

    expect($section)->toContain('Whitby')->toContain('Overflow data out of date')->not->toContain('No spills reported nearby');
});

it('lists the nearest other beaches and a river spot on a beach page', function () {
    $region = nearbyRegion();
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Esk', 'slug' => 'esk']);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ruswarp', 'slug' => 'ruswarp', 'latitude' => 54.45, 'longitude' => -0.62]);
    $whitby = BathingWater::factory()->create(['region_id' => $region->id, 'name' => 'Whitby', 'slug' => 'whitby', 'latitude' => 54.49, 'longitude' => -0.62]);
    foreach (['Sandsend' => 54.5, 'Runswick Bay' => 54.53, 'Robin Hoods Bay' => 54.43, 'Scarborough North Bay' => 54.29] as $name => $latitude) {
        BathingWater::factory()->create(['region_id' => $region->id, 'name' => $name, 'slug' => Str::slug($name), 'latitude' => $latitude, 'longitude' => -0.62]);
    }
    BathingWater::factory()->create(['region_id' => $region->id, 'name' => 'Bridlington North', 'slug' => 'bridlington-north', 'latitude' => 54.1, 'longitude' => -0.2]);

    $response = $this->get(route('beaches.show', [$region, $whitby]))
        ->assertOk()
        ->assertSeeInOrder(['Nearby', 'Sandsend', 'Runswick Bay', 'Robin Hoods Bay', 'On the River Esk', 'Ruswarp'])
        ->assertSee('Beaches in Yorkshire')
        ->assertSee('Rivers in Yorkshire');

    // Scarborough is the fourth nearest, Bridlington beyond 30 km.
    expect(nearbySection($response))->not->toContain('Scarborough North Bay')->not->toContain('Bridlington North')->not->toContain('>Whitby<');
});

it('links a lake page to its region listings', function () {
    $region = Region::factory()->create(['name' => 'North West', 'slug' => 'north-west']);
    $lake = Lake::factory()->create(['region_id' => $region->id, 'name' => 'Windermere', 'slug' => 'windermere', 'latitude' => 54.36, 'longitude' => -2.94]);
    Lake::factory()->create(['region_id' => $region->id, 'name' => 'Coniston Water', 'slug' => 'coniston-water', 'latitude' => 54.35, 'longitude' => -3.07]);

    $this->get(route('lakes.show', [$region, $lake]))
        ->assertOk()
        ->assertSeeInOrder(['Nearby', 'Lake', 'Coniston Water', 'km away'])
        ->assertSee('Lakes in the North West')
        ->assertDontSee('Rivers in the North West');
});
