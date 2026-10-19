<?php

use App\Enums\BathingWaterClassification;
use App\Enums\BathingWaterKind;
use App\Models\BathingWater;
use App\Models\BathingWaterSample;
use App\Models\Lake;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        ->assertSee('Samples failed the minimum standard, and the advice is against bathing.')
        ->assertSeeInOrder(['E. coli', '310'])
        ->assertSee('Storm overflows within 3 km')
        ->assertSee('UUP00001');
});

it('shows when each nearby overflow last started spilling and how long it lasted', function () {
    [$region, $beach] = waterBodies();
    $monitor = OverflowMonitor::query()->where('external_id', 'UUP00001')->sole();
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subHours(11), 'ended_at' => now()->subHours(11)->addMinutes(370)]);

    $this->get(route('beaches.show', [$region, $beach]))
        ->assertOk()
        ->assertSee('Last spill started')
        ->assertSee('11 hours ago')
        ->assertSee('6 h 10 min')
        ->assertSee('lasted 6 h 10 min');
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
        ->assertSee('We don\'t have a level gauge for this lake.');
});

it('scopes beaches and lakes to their region', function () {
    [, $beach, $lake] = waterBodies();
    $other = Region::factory()->create(['slug' => 'thames-valley']);

    $this->get("/thames-valley/beaches/{$beach->slug}")->assertNotFound();
    $this->get("/thames-valley/lakes/{$lake->slug}")->assertNotFound();
});

it('adds Beaches and Lakes menus that list only regions with some', function () {
    waterBodies();
    Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);

    $html = $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['data-nav-menu="beaches"', 'href="'.route('hubs.beaches').'"', 'data-nav-menu="lakes"', 'href="'.route('hubs.lakes').'"'], false)
        ->getContent();

    // The regions and places are built from the page's JSON when a menu opens, not written into the header.
    preg_match('/<script type="application\/json" id="nav-menus">(.*?)<\/script>/s', $html, $match);
    $menus = json_decode($match[1], true);

    expect(collect($menus['beaches'])->pluck('slug')->all())->toBe(['north-west'])
        ->and($menus['beaches'][0]['url'])->toBe('/north-west/beaches')
        ->and($menus['beaches'][0]['groups'])->toBe([['Blackpool', [['Blackpool North', '/north-west/beaches/blackpool-north']]]])
        ->and($menus['lakes'][0]['groups'])->toBe([['Lakes', [['Windermere', '/north-west/lakes/windermere']]]])
        ->and(str_contains(Str::before($html, '<main'), 'Blackpool North'))->toBeFalse();
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

it('counts beaches and lakes on the home page and leaves out places it has none of', function () {
    waterBodies();

    $this->get(route('home'))
        ->assertOk()
        // The intro's count names only the kinds of place there are.
        ->assertSee('bathing water results for 1 beach and 1 lake.')
        ->assertSee('Bathing water results');
});

it('titles beaches and lakes the way people search for them', function () {
    [$region, $beach, $lake] = waterBodies();
    $bay = BathingWater::factory()->create(['region_id' => $region->id, 'name' => "Robin Hood's Bay", 'slug' => 'robin-hoods-bay']);

    $this->get(route('beaches.show', [$region, $beach]))->assertOk()
        ->assertSee('<title>Blackpool North beach: bathing water quality and swimming - '.config('app.name').'</title>', false)
        ->assertSee('"name":"Beaches","item":"'.route('beaches.index', $region).'"', false);
    // Apostrophes stay as typed in the title and meta tags, which some readers would otherwise show as "&#039;".
    $head = Str::before($this->get(route('beaches.show', [$region, $bay]))->assertOk()->getContent(), '</head>');
    expect($head)->toContain("<title>Robin Hood's Bay: bathing water quality and swimming - How's the Water?</title>")
        ->toContain('<meta property="og:title" content="Robin Hood\'s Bay: bathing water quality and swimming">')
        ->toContain('<meta property="og:site_name" content="How\'s the Water?">')
        ->not->toContain('&#039;');
    $this->get(route('lakes.show', [$region, $lake]))->assertOk()->assertSee('<title>Windermere: swimming, water quality and lake level - '.config('app.name').'</title>', false);
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

it('renders the menus from one cached lookup however many regions there are', function () {
    waterBodies();
    $this->get(route('about'))->assertOk();

    foreach (range(1, 3) as $index) {
        Lake::factory()->create(['region_id' => Region::factory()->create(['slug' => "extra-{$index}"])->id]);
    }

    DB::enableQueryLog();
    $this->get(route('about'))->assertOk();
    // SQLite quotes names with double quotes and MySQL with backticks, so both are stripped before matching.
    $menuQueries = collect(DB::getQueryLog())->filter(fn (array $query) => preg_match('/from (lakes|bathing_waters)\b/', str_replace(['`', '"'], '', $query['query'])) === 1);

    expect($menuQueries)->toBeEmpty();
});

it('rebuilds the menus when regions refresh after an ingest', function () {
    [$region] = waterBodies();
    $this->get(route('search'))->assertOk()->assertDontSee('Coniston Water');

    Lake::factory()->create(['region_id' => $region->id, 'name' => 'Coniston Water', 'slug' => 'coniston-water']);
    $this->artisan('regions:refresh --all')->assertSuccessful();

    $this->get(route('search'))->assertOk()->assertSee('Coniston Water');
});

it('reads a region\'s beach conditions from the cache in one batch', function () {
    [$region] = waterBodies();
    BathingWater::factory()->count(5)->create(['region_id' => $region->id]);
    $this->get(route('beaches.index', $region))->assertOk();

    DB::enableQueryLog();
    $this->get(route('beaches.index', $region))->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThan(12);
});

it('leads a beach\'s description with its own classification and nearby overflows', function () {
    [$region, $beach] = waterBodies();
    $beach->update(['classification_year' => 2025]);

    $this->get(route('beaches.show', [$region, $beach]))
        ->assertOk()
        ->assertSee('<meta name="description" content="Blackpool North beach: rated Poor in 2025 by the Environment Agency, with 1 storm overflow within 3 km. Samples, pollution forecasts and nearby spills.">', false);
});

it('moves the London lakes out of Thames Valley, and redirects their old addresses and Paignton Sands\'s', function () {
    $london = Region::factory()->create(['slug' => 'london']);
    $thamesValley = Region::factory()->create(['slug' => 'thames-valley']);
    $heath = Lake::factory()->create(['region_id' => $london->id, 'name' => 'Hampstead Heath', 'slug' => 'hampstead-heath']);
    $ponds = Lake::factory()->create(['region_id' => $thamesValley->id, 'name' => 'Hampstead Heath Ponds', 'slug' => 'hampstead-heath-ponds']);
    $serpentine = Lake::factory()->create(['region_id' => $thamesValley->id, 'name' => 'The Serpentine', 'slug' => 'the-serpentine']);
    $ladies = BathingWater::factory()->kind(BathingWaterKind::Lake)->create(['region_id' => $thamesValley->id, 'lake_id' => $ponds->id, 'name' => 'Hampstead Heath (Ladies Pond)']);
    $hydePark = BathingWater::factory()->kind(BathingWaterKind::Lake)->create(['region_id' => $thamesValley->id, 'lake_id' => $serpentine->id, 'name' => 'The Serpentine - Hyde Park']);

    (require database_path('migrations/2026_10_19_075157_move_the_london_lakes_out_of_thames_valley.php'))->up();

    expect(Lake::query()->find($ponds->id))->toBeNull()
        ->and($ladies->fresh()->only(['lake_id', 'region_id']))->toBe(['lake_id' => $heath->id, 'region_id' => $london->id])
        ->and($serpentine->fresh()->region_id)->toBe($london->id)
        ->and($hydePark->fresh()->region_id)->toBe($london->id);

    $this->get('/thames-valley/lakes/hampstead-heath-ponds')->assertRedirect('/london/lakes/hampstead-heath')->assertStatus(301);
    $this->get('/thames-valley/lakes/the-serpentine/spills')->assertRedirect('/london/lakes/the-serpentine/spills')->assertStatus(301);
    $this->get('/south-west/beaches/paignton-paignton-sands/feed')->assertRedirect('/south-west/beaches/paignton-sands/feed')->assertStatus(301);
    $this->get(route('lakes.show', [$london, $serpentine]))->assertOk();
});
