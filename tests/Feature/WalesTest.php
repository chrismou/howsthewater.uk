<?php

use App\Actions\Ingest\SyncBathingWaterHistory;
use App\Actions\Ingest\SyncBathingWaters;
use App\Enums\Agency;
use App\Enums\SpillRecency;
use App\Models\BathingWater;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use App\Services\Conditions\SpotConditionsService;
use App\Services\Conditions\WaterBodyConditionsService;
use Database\Seeders\RiverSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function wales(): Region
{
    return Region::factory()->create(['name' => 'Wales', 'slug' => 'wales', 'overflow_feeds' => [], 'bathing_water_districts' => ['the Vale of Glamorgan', 'Gwynedd']]);
}

/**
 * A site from Wales's register, which labels sites rather than naming them.
 *
 * @return array<string, mixed>
 */
function welshBathingWater(string $id, string $label, float $lat, float $lng): array
{
    return [
        'eubwidNotation' => $id,
        'label' => [['_value' => $label, '_lang' => 'en']],
        'type' => ['http://environment.data.gov.uk/def/bathing-water/BathingWater', 'http://environment.data.gov.uk/def/bathing-water/CoastalBathingWater'],
        'district' => [['name' => ['_value' => 'the Vale of Glamorgan', '_lang' => 'en']], 'http://statistics.data.gov.uk/id/statistical-geography/W06000014'],
        'samplingPoint' => ['_about' => 'http://environment.data.gov.uk/wales/bathing-waters/so/SamplingPoint/'.substr($id, -5), 'lat' => $lat, 'long' => $lng],
        'latestComplianceAssessment' => ['complianceClassification' => ['name' => ['_value' => 'Good']], 'sampleYear' => ['ordinalYear' => 2025]],
    ];
}

it('reads Wales\'s bathing water register from its own path, as Natural Resources Wales sites', function () {
    $wales = wales();
    Http::fake([
        'environment.data.gov.uk/wales/bathing-waters/doc/bathing-water.json*' => Http::response(['result' => ['items' => [welshBathingWater('ukl2202-36050', 'Penarth Beach', 51.4339, -3.1689)]]]),
        'environment.data.gov.uk/wales/bathing-waters/doc/bathing-water-quality/in-season/sample.json*' => Http::response(['result' => ['items' => [
            ['sampleDateTime' => ['inXSDDateTime' => ['_value' => '2026-09-03T10:00:00']], 'escherichiaColiCount' => 173, 'escherichiaColiQualifier' => ['countQualifierNotation' => '=']],
        ]]]),
        'environment.data.gov.uk/wales/bathing-waters/data/bathing-water-quality/compliance-rBWD/*' => Http::response(['result' => ['items' => [
            ['sampleYear' => ['ordinalYear' => 2025], 'complianceClassification' => ['name' => ['_value' => 'Good']]],
        ]]]),
    ]);

    expect(app(SyncBathingWaters::class)->handleWales()['sites'])->toBe(1);

    $penarth = BathingWater::query()->sole();
    expect($penarth->name)->toBe('Penarth Beach')
        ->and($penarth->agency)->toBe(Agency::NaturalResourcesWales)
        ->and($penarth->region_id)->toBe($wales->id)
        ->and($penarth->district)->toBe('the Vale of Glamorgan')
        ->and($penarth->profile_url)->toBe('https://environment.data.gov.uk/wales/bathing-waters/profiles/profile.html?site=ukl2202-36050');

    expect(app(SyncBathingWaterHistory::class)->handle())->toMatchArray(['sites' => 1, 'samples' => 1]);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/wales/bathing-waters/data/bathing-water-quality/compliance-rBWD/point/36050'));
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'gov.uk/doc/bathing-water-quality'));

    $this->get(route('beaches.show', [$wales, $penarth]))->assertOk()->assertSee('Natural Resources Wales profile');
});

it('says overflow data is not available where we do not read the water company feed, never that there are none', function () {
    $wales = wales();
    $river = River::factory()->create(['region_id' => $wales->id, 'name' => 'River Wye', 'slug' => 'wye']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Hay-on-Wye', 'slug' => 'hay-on-wye']);
    $beach = BathingWater::factory()->create(['region_id' => $wales->id, 'agency' => Agency::NaturalResourcesWales, 'name' => 'Penarth Beach', 'slug' => 'penarth-beach']);

    expect(app(SpotConditionsService::class)->build($spot)->spillRecency)->toBe(SpillRecency::NotCovered)
        ->and(app(WaterBodyConditionsService::class)->buildBeach($beach)->overflows->spillRecency)->toBe(SpillRecency::NotCovered);

    $this->get(route('spots.show', [$wales, $river, $spot]))->assertOk()
        ->assertSee('Overflow data not available here yet')
        ->assertSee('so storm overflows upstream of Hay-on-Wye on the River Wye are not shown')
        ->assertDontSee('No monitored overflows upstream')
        ->assertDontSee('No spills reported')
        ->assertDontSee('discharging on');

    $this->get(route('beaches.show', [$wales, $beach]))->assertOk()
        ->assertSee('Overflow data not available here yet')
        ->assertDontSee('No water company monitors within');

    $this->get(route('regions.show', $wales))->assertOk()
        ->assertSee('Overflow data not available yet')
        ->assertDontSee('Monitor data is out of date');

    $this->get(route('rivers.show', [$wales, $river]))->assertOk()
        ->assertSee("We do not read the water company's overflow data here yet, so storm overflows on the River Wye are not shown.", false)
        ->assertSee('<title>River Wye levels and swimming spots', false)
        ->assertDontSee('upstream monitors');
});

it('still reports no monitored overflows in a region whose feed we do read', function () {
    $region = Region::factory()->create(['slug' => 'yorkshire', 'overflow_feeds' => ['yorkshire_water']]);
    $spot = Spot::factory()->create(['river_id' => River::factory()->create(['region_id' => $region->id])->id]);
    OverflowMonitor::factory()->create(['latitude' => 0, 'longitude' => 0]);

    expect(app(SpotConditionsService::class)->build($spot)->spillRecency)->toBe(SpillRecency::NoData);
});

it('seeds Welsh rivers under their OS names, with slugs that drop River or Afon', function () {
    $rivers = collect(RiverSeeder::rivers()['wales'])->keyBy('slug');

    expect($rivers['teifi']['name'])->toBe('Afon Teifi')
        ->and($rivers['usk']['name'])->toBe('River Usk')
        ->and($rivers['usk']['aliases'])->toContain('Afon Wysg')
        ->and($rivers['taff']['mouth'])->toBe([51.4640, -3.1690]);
});
