<?php

use App\Enums\BathingWaterKind;
use App\Enums\PollutionRisk;
use App\Models\BathingWater;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/**
 * The home page's "Right now at popular swimming spots" section alone, as the menus and maps name every place too.
 */
function swimmingSpotsSection(TestResponse $response): string
{
    preg_match('/<section aria-labelledby="swimming-now".*?<\/section>/s', (string) $response->assertOk()->getContent(), $section);

    return $section[0] ?? '';
}

it('lists what is happening at popular swimming spots first, most pressing first, then the well-known spots as they are', function () {
    $region = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Wharfe', 'slug' => 'wharfe']);

    // A beach with an overflow discharging within 3 km.
    $beach = BathingWater::factory()->create(['region_id' => $region->id, 'name' => 'Runswick Bay', 'slug' => 'runswick-bay', 'latitude' => 54.53, 'longitude' => -0.73]);
    OverflowMonitor::factory()->discharging()->create(['latitude' => 54.531, 'longitude' => -0.731]);

    // A river spot that is a designated bathing water, with a spill upstream that ended 3 hours ago.
    $lido = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Cromwheel', 'slug' => 'cromwheel', 'river_metres' => 50000]);
    BathingWater::factory()->kind(BathingWaterKind::River)->create(['region_id' => $region->id, 'spot_id' => $lido->id, 'name' => 'Wharfe at Cromwheel']);
    $upstream = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 40000]);
    SpillEvent::factory()->create(['overflow_monitor_id' => $upstream->id, 'started_at' => now()->subHours(5), 'ended_at' => now()->subHours(3)]);

    // A well-known spot with nothing happening, and an ordinary spot with an overflow discharging upstream.
    Spot::factory()->featured()->create(['river_id' => $river->id, 'name' => 'Burnsall', 'slug' => 'burnsall', 'river_metres' => 20000]);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ordinary Weir', 'slug' => 'ordinary-weir', 'river_metres' => 90000]);
    OverflowMonitor::factory()->discharging()->create(['river_id' => $river->id, 'river_metres' => 80000]);

    $response = $this->get(route('home'))
        ->assertSeeInOrder(['Right now at popular swimming spots', 'All swimming spots', 'with anything happening there first', 'Runswick Bay', 'Beach, Yorkshire', 'Overflow discharging nearby now', 'Cromwheel', 'River Wharfe, Yorkshire', 'Burnsall'])
        ->assertSee(route('beaches.show', [$region, $beach]), false);

    // Only popular spots: the ordinary one is on the spills pages instead.
    expect(swimmingSpotsSection($response))->toContain('Cromwheel')->not->toContain('Ordinary Weir');
});

it('says when nothing is happening at popular swimming spots before listing the well-known ones', function () {
    $river = River::factory()->create(['region_id' => Region::factory()->create(['slug' => 'yorkshire'])->id, 'name' => 'River Wharfe']);
    Spot::factory()->featured()->create(['river_id' => $river->id, 'name' => 'Burnsall', 'slug' => 'burnsall']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Right now at popular swimming spots', 'Nothing to report at 1 designated bathing waters and well-known river spots', 'Burnsall']);
});

it('notes an increased pollution risk forecast in force at a beach in the bathing season', function () {
    $this->travelTo(Carbon::parse('2026-07-10 09:00', 'Europe/London'));
    $region = Region::factory()->create(['slug' => 'north-east']);
    BathingWater::factory()->create(['region_id' => $region->id, 'name' => 'Tynemouth Long Sands', 'risk_level' => PollutionRisk::Increased, 'risk_expires_at' => now()->addHours(10)]);
    BathingWater::factory()->create(['region_id' => $region->id, 'name' => 'Bamburgh Castle', 'latitude' => 55.61, 'longitude' => -1.71]);

    $response = $this->get(route('home'))
        ->assertSeeInOrder(['Right now at popular swimming spots', 'Tynemouth Long Sands', 'Increased pollution risk forecast today']);

    expect(swimmingSpotsSection($response))->not->toContain('Bamburgh Castle');
});

it('shows a block per region on the swimming page, and serves every place with its status for the map and near me', function () {
    $yorkshire = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    $northEast = Region::factory()->create(['name' => 'North East', 'slug' => 'north-east']);
    $river = River::factory()->create(['region_id' => $yorkshire->id, 'name' => 'River Wharfe', 'slug' => 'wharfe']);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Otley', 'slug' => 'otley', 'river_metres' => 50000]);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ilkley', 'slug' => 'ilkley', 'river_metres' => 40000]);
    OverflowMonitor::factory()->discharging()->create(['river_id' => $river->id, 'river_metres' => 30000]);
    BathingWater::factory()->create(['region_id' => $northEast->id, 'name' => 'Tynemouth Long Sands', 'slug' => 'tynemouth-long-sands', 'district' => 'North Tyneside']);

    $this->get(route('swimming'))
        ->assertOk()
        ->assertSee('<title>Swimming spots near you: bathing water quality and spills - How\'s the Water?</title>', false)
        // A block per region, by name, linking to the region and the listings it has; no place rows in the page.
        ->assertSeeInOrder(['By region', 'North East', '1 beach', 'No overflow discharging upstream or nearby now', 'Full region', 'Beaches', 'Yorkshire', '2 river spots', '2 with an overflow discharging upstream or nearby now', 'Full region', 'Rivers'])
        ->assertSee('href="'.route('beaches.index', $northEast).'"', false)
        ->assertDontSee('href="'.route('rivers.index', $northEast).'"', false)
        ->assertDontSee('href="/yorkshire/rivers/wharfe/otley"', false)
        ->assertSee('data-map-markers-url="'.route('swimming.places').'"', false)
        // The controls and the nearest-first list wait for JavaScript and the visitor's location.
        ->assertSee('data-swimming-controls hidden', false)
        ->assertSee('data-swimming-nearest hidden', false);

    $places = $this->get(route('swimming.places'))->assertOk()->assertHeader('Cache-Control', 'max-age=60, public, s-maxage=300, stale-while-revalidate=300')->json();

    expect(collect($places)->pluck('label')->all())->toBe(['Tynemouth Long Sands', 'Ilkley', 'Otley'])
        ->and(collect($places)->firstWhere('label', 'Otley'))->toBe([
            'lat' => round(Spot::query()->where('slug', 'otley')->value('latitude'), 5),
            'lng' => round(Spot::query()->where('slug', 'otley')->value('longitude'), 5),
            'label' => 'Otley',
            'url' => '/yorkshire/rivers/wharfe/otley',
            'kind' => 'place',
            'recency' => 'discharging_now',
            'type' => 'river',
            'region' => 'Yorkshire',
            'detail' => 'River Wharfe',
            'pill' => 'Discharging upstream now',
            'tone' => 'red',
        ])
        ->and(collect($places)->firstWhere('label', 'Tynemouth Long Sands'))->toMatchArray(['type' => 'beach', 'detail' => 'Beach, classified Good', 'region' => 'North East']);

    $this->get(route('home'))->assertSee('href="'.route('swimming').'"', false);
    $this->get(route('sitemap'))->assertSee('<loc>'.route('swimming').'</loc>', false)->assertDontSee('places.json');
    $this->get(route('privacy'))->assertSee('Your location is never sent to the site or stored.');
});
