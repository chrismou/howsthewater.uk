<?php

use App\Enums\BathingWaterClassification;
use App\Enums\BathingWaterKind;
use App\Models\BathingWater;
use App\Models\BathingWaterSample;
use App\Models\Lake;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;

/**
 * @return array{0: Region, 1: BathingWater, 2: Lake}
 */
function waterBodies(): array
{
    $region = Region::factory()->create(['name' => 'North West', 'slug' => 'north-west']);
    River::factory()->create(['region_id' => $region->id]);
    $beach = BathingWater::factory()->create(['region_id' => $region->id, 'name' => 'Blackpool North', 'slug' => 'blackpool-north', 'district' => 'Blackpool', 'classification' => BathingWaterClassification::Poor]);
    BathingWaterSample::factory()->create(['bathing_water_id' => $beach->id, 'e_coli' => 310, 'e_coli_qualifier' => '=']);
    $lake = Lake::factory()->create(['region_id' => $region->id, 'name' => 'Windermere', 'slug' => 'windermere']);
    BathingWater::factory()->kind(BathingWaterKind::Lake)->create(['region_id' => $region->id, 'lake_id' => $lake->id, 'name' => 'Windermere, Fellfoot', 'classification' => BathingWaterClassification::Excellent]);
    OverflowMonitor::factory()->create(['latitude' => $beach->latitude + 0.005, 'longitude' => $beach->longitude, 'external_id' => 'UUP00001', 'receiving_watercourse' => 'Irish Sea']);

    return [$region, $beach, $lake];
}

it('shows a beach with its classification, latest sample and nearby overflows', function () {
    [$region, $beach] = waterBodies();

    $this->get(route('beaches.show', [$region, $beach]))
        ->assertOk()
        ->assertSee('Blackpool North')
        ->assertSee('Poor')
        ->assertSee('The Environment Agency advises against bathing.')
        ->assertSeeInOrder(['E. coli', '310'])
        ->assertSee('Storm overflows within 3 km')
        ->assertSee('UUP00001');
});

it('lists a region\'s beaches and lakes, and 404s regions with none', function () {
    [$region] = waterBodies();
    $empty = Region::factory()->create(['slug' => 'yorkshire']);

    $this->get(route('beaches.index', $region))->assertOk()->assertSee('Blackpool North');
    $this->get(route('lakes.index', $region))->assertOk()->assertSee('Windermere')->assertSee('Excellent');
    $this->get(route('beaches.index', $empty))->assertNotFound();
    $this->get(route('lakes.index', $empty))->assertNotFound();
});

it('shows a lake with its bathing sites', function () {
    [$region, , $lake] = waterBodies();

    $this->get(route('lakes.show', [$region, $lake]))
        ->assertOk()
        ->assertSee('Windermere, Fellfoot')
        ->assertSee('Excellent')
        ->assertSee('No Environment Agency gauge reads this lake\'s level.', false);
});

it('scopes beaches and lakes to their region', function () {
    [, $beach, $lake] = waterBodies();
    $other = Region::factory()->create(['slug' => 'thames-valley']);

    $this->get("/thames-valley/beaches/{$beach->slug}")->assertNotFound();
    $this->get("/thames-valley/lakes/{$lake->slug}")->assertNotFound();
});

it('adds Beaches and Lakes menus that list only regions with some', function () {
    [$region] = waterBodies();
    Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Beaches', 'data-nav-region-tab="north-west"', 'Blackpool', 'Blackpool North'], false)
        ->assertSeeInOrder(['Lakes', 'data-nav-region-tab="north-west"', 'Windermere'], false)
        ->assertSee(route('beaches.show', [$region, 'blackpool-north']));
});

it('shows the bathing water record on a spot that is a designated bathing water', function () {
    $region = Region::factory()->create(['slug' => 'yorkshire']);
    $river = River::factory()->create(['region_id' => $region->id, 'slug' => 'wharfe']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'slug' => 'ilkley']);
    BathingWater::factory()->kind(BathingWaterKind::River)->create(['region_id' => $region->id, 'spot_id' => $spot->id, 'name' => 'Wharfe at Cromwheel, Ilkley']);

    $this->get(route('spots.show', [$region, $river, $spot]))
        ->assertOk()
        ->assertSee('Designated bathing water')
        ->assertSee('Wharfe at Cromwheel, Ilkley');

    $this->getJson(route('api.v1.spots.show', [$region, $river, $spot]))
        ->assertOk()
        ->assertJsonPath('data.bathing_water.name', 'Wharfe at Cromwheel, Ilkley');
});

it('finds beaches and lakes in search and lists them in the sitemap', function () {
    [$region, $beach, $lake] = waterBodies();

    $this->get(route('search', ['q' => 'blackpool']))->assertOk()->assertSee(route('beaches.show', [$region, $beach]));
    $this->get(route('search', ['q' => 'winder']))->assertOk()->assertSee(route('lakes.show', [$region, $lake]));
    $this->get(route('sitemap'))->assertOk()->assertSee(route('beaches.show', [$region, $beach]))->assertSee(route('lakes.show', [$region, $lake]));
});

it('serves beaches and lakes from the API', function () {
    [$region, $beach, $lake] = waterBodies();

    $this->getJson(route('api.v1.beaches.show', [$region, $beach]))
        ->assertOk()
        ->assertJsonPath('data.bathing_water.classification', 'Poor')
        ->assertJsonPath('data.bathing_water.samples.0.e_coli_per_100ml', 310)
        ->assertJsonPath('data.nearby_overflows.radius_metres', 3000)
        ->assertJsonPath('data.nearby_overflows.monitors.0.id', 'UUP00001');

    $this->getJson(route('api.v1.lakes.show', [$region, $lake]))
        ->assertOk()
        ->assertJsonPath('data.name', 'Windermere')
        ->assertJsonPath('data.level', null)
        ->assertJsonPath('data.bathing_waters.0.name', 'Windermere, Fellfoot');

    $this->getJson(route('api.v1.beaches.index', $region))->assertOk()->assertJsonCount(1, 'data');
});
