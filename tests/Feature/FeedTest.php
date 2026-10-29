<?php

use App\Enums\BathingWaterKind;
use App\Enums\FloodSeverity;
use App\Enums\PollutionRisk;
use App\Models\BathingWater;
use App\Models\BathingWaterSample;
use App\Models\FloodArea;
use App\Models\FloodWarning;
use App\Models\Lake;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use App\Services\Conditions\RegionSummaries;
use App\Services\Conditions\SpotConditionsService;

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

it('lists each flood warning on the river once in the river feed, from the cached region summary', function () {
    [$region, $river, $spot] = feedSpot();
    $downstream = Spot::factory()->create(['river_id' => $river->id, 'slug' => 'wetherby', 'river_metres' => 80000]);
    $area = FloodArea::factory()->hasAttached($spot)->hasAttached($downstream)->create(['code' => '122WAF943', 'label' => 'Lower River Wharfe']);
    FloodWarning::factory()->severity(FloodSeverity::FloodWarning)->create(['flood_area_id' => $area->id]);
    app(RegionSummaries::class)->refresh($region);

    // Every spot's full conditions do not fit in memory on long rivers, so the feed must not read them.
    $this->mock(SpotConditionsService::class)->shouldNotReceive('for', 'forMany');

    $xml = $this->get(route('rivers.feed', [$region, $river]))->assertOk()->getContent();

    expect(substr_count($xml, 'Flood warning in force: Lower River Wharfe'))->toBe(1);
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

it('lists nearby overflows, recent samples and increased pollution forecasts in the beach feed', function () {
    $region = Region::factory()->create(['slug' => 'north-west']);
    $beach = BathingWater::factory()->create([
        'region_id' => $region->id, 'name' => 'Blackpool North', 'slug' => 'blackpool-north',
        'season_starts_on' => now()->subMonth(), 'season_ends_on' => now()->addMonth(),
        'risk_level' => PollutionRisk::Increased, 'risk_expires_at' => now()->addHours(20),
    ]);
    $recent = BathingWaterSample::factory()->create(['bathing_water_id' => $beach->id, 'sampled_at' => now()->subDays(3), 'e_coli' => 310, 'enterococci' => 45, 'enterococci_qualifier' => '=']);
    $old = BathingWaterSample::factory()->create(['bathing_water_id' => $beach->id, 'sampled_at' => now()->subDays(40)]);
    $near = OverflowMonitor::factory()->create(['latitude' => $beach->latitude + 0.01, 'longitude' => $beach->longitude, 'external_id' => 'UUP00001']);
    $far = OverflowMonitor::factory()->create(['latitude' => $beach->latitude + 0.1, 'longitude' => $beach->longitude, 'external_id' => 'UUP00002']);
    SpillEvent::factory()->ongoing()->create(['overflow_monitor_id' => $near->id]);
    SpillEvent::factory()->ongoing()->create(['overflow_monitor_id' => $far->id]);

    $this->get(route('beaches.feed', [$region, $beach]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
        ->assertSee('<title>Blackpool North - '.e(config('app.name')).'</title>', false)
        ->assertSee('Not a statement that the water is safe or clean.')
        ->assertSee('Storm overflow UUP00001 started discharging, 1.1 km away')
        ->assertDontSee('UUP00002')
        ->assertSee("sample-{$recent->id}")
        ->assertSee('E. coli 310 and intestinal enterococci 45 per 100 ml')
        ->assertDontSee("sample-{$old->id}")
        ->assertSee('Increased pollution risk forecast')
        ->assertSee("risk-{$beach->id}-");

    $this->get(route('beaches.show', [$region, $beach]))->assertOk()->assertSee(route('beaches.feed', [$region, $beach]));
});

it('leaves out pollution forecasts that are normal or out of season', function () {
    $region = Region::factory()->create();
    $normal = BathingWater::factory()->create(['region_id' => $region->id, 'season_starts_on' => now()->subMonth(), 'season_ends_on' => now()->addMonth(), 'risk_level' => PollutionRisk::Normal, 'risk_expires_at' => now()->addDay()]);
    $offSeason = BathingWater::factory()->create(['region_id' => $region->id, 'season_starts_on' => now()->subMonths(3), 'season_ends_on' => now()->subMonth(), 'risk_level' => PollutionRisk::Increased, 'risk_expires_at' => now()->addDay()]);

    $this->get(route('beaches.feed', [$region, $normal]))->assertOk()->assertDontSee('Increased pollution risk forecast');
    $this->get(route('beaches.feed', [$region, $offSeason]))->assertOk()->assertDontSee('Increased pollution risk forecast');
});

it('names each bathing site in the lake feed', function () {
    $region = Region::factory()->create(['slug' => 'north-west']);
    $lake = Lake::factory()->create(['region_id' => $region->id, 'name' => 'Windermere', 'slug' => 'windermere']);
    $site = BathingWater::factory()->kind(BathingWaterKind::Lake)->create(['region_id' => $region->id, 'lake_id' => $lake->id, 'name' => 'Windermere, Fellfoot']);
    BathingWaterSample::factory()->create(['bathing_water_id' => $site->id, 'sampled_at' => now()->subDays(2)]);

    $this->get(route('lakes.feed', [$region, $lake]))
        ->assertOk()
        ->assertSee('<title>Windermere - '.e(config('app.name')).'</title>', false)
        ->assertSee('Bathing water sample at Windermere, Fellfoot taken');

    $this->get(route('lakes.show', [$region, $lake]))->assertOk()->assertSee(route('lakes.feed', [$region, $lake]));
});

it('404s beach and lake feeds from another region', function () {
    $region = Region::factory()->create();
    $other = Region::factory()->create();
    $beach = BathingWater::factory()->create(['region_id' => $other->id]);
    $lake = Lake::factory()->create(['region_id' => $other->id]);

    $this->get(route('beaches.feed', [$region, $beach]))->assertNotFound();
    $this->get(route('lakes.feed', [$region, $lake]))->assertNotFound();
});

it('gives each overflow a feed of its spills under its name, linking to its page', function () {
    $region = Region::factory()->create();
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Wharfe']);
    $monitor = OverflowMonitor::factory()->create(['external_id' => 'YWS02215', 'return_name' => 'Ellerton CSO', 'river_id' => $river->id, 'river_metres' => 1000]);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subHours(5), 'ended_at' => now()->subHours(3)]);
    $orphan = OverflowMonitor::factory()->create(['river_id' => null]);

    $this->get(route('overflows.feed', $monitor))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
        ->assertSee('<title>Ellerton CSO - ', false)
        ->assertSee('Ellerton CSO started discharging on the River Wharfe')
        ->assertSee('Ellerton CSO stopped discharging after 2 h 0 min')
        ->assertSee('<link>'.route('overflows.show', $monitor).'</link>', false);
    $this->get(route('overflows.feed', $orphan))->assertNotFound();
    $this->get(route('overflows.show', $monitor))->assertSee(route('overflows.feed', $monitor), false);
});

it('keeps an overflow feed valid when a name carries a control character, and names unnamed overflows plainly', function () {
    $region = Region::factory()->create();
    $river = River::factory()->create(['region_id' => $region->id]);
    $named = OverflowMonitor::factory()->create(['return_name' => "Bad\x0BName CSO", 'river_id' => $river->id]);
    $unnamed = OverflowMonitor::factory()->create(['external_id' => 'YWS00008', 'company' => 'Anglian Water Services', 'river_id' => $river->id]);

    $xml = $this->get(route('overflows.feed', $named))->assertOk()->getContent();

    expect(simplexml_load_string($xml))->not->toBeFalse()
        ->and($xml)->toContain('BadName CSO');
    $this->get(route('overflows.feed', $unnamed))->assertSee('Each spill from Anglian Water storm overflow YWS00008 as its monitor reports');
});
