<?php

use App\Enums\Agency;
use App\Enums\OverflowStatus;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Search results show about 60 characters of a title and 160 of a description.
 *
 * @return array{title: string, description: string}
 */
function pageMetadata(TestResponse $response): array
{
    $html = (string) $response->assertOk()->getContent();
    preg_match('/<title>(.*?)<\/title>/s', $html, $title);
    preg_match('/<meta name="description" content="([^"]*)"/', $html, $description);

    return [
        'title' => html_entity_decode(trim($title[1]), ENT_QUOTES),
        'description' => html_entity_decode($description[1], ENT_QUOTES),
    ];
}

it('keeps the home and site-wide titles and descriptions short however many regions there are', function () {
    foreach (['Yorkshire', 'Thames Valley', 'North West', 'North East', 'Midlands', 'Scotland', 'South West England', 'East Anglia and Lincolnshire', 'Wales'] as $name) {
        River::factory()->create(['region_id' => Region::factory()->create(['name' => $name, 'slug' => Str::slug($name)])->id]);
    }

    foreach ([route('home'), route('hubs.rivers'), route('hubs.beaches'), route('hubs.lakes'), route('search'), route('about'), route('privacy'), route('terms')] as $url) {
        $metadata = pageMetadata($this->get($url));

        expect(mb_strlen(Str::beforeLast($metadata['title'], ' - '.config('app.name'))))->toBeLessThanOrEqual(60, "Title of {$url}: {$metadata['title']}")
            ->and(mb_strlen($metadata['description']))->toBeLessThanOrEqual(160, "Description of {$url}: {$metadata['description']}");
    }
});

it('keeps place descriptions short for long names, and ends every title with the site name', function () {
    $region = Region::factory()->create(['name' => 'North East', 'slug' => 'north-east']);
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River South Tyne', 'slug' => 'south-tyne']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Linton Falls, Grassington', 'slug' => 'linton-falls']);
    $short = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Alston', 'slug' => 'alston']);
    $beach = BathingWater::factory()->create(['region_id' => $region->id, 'name' => 'Tynemouth Long Sands North', 'slug' => 'long-sands-north']);
    $scottish = BathingWater::factory()->create(['region_id' => $region->id, 'agency' => Agency::Sepa, 'name' => 'Aberdour Harbour (Black Sands)', 'slug' => 'aberdour-harbour']);
    $lake = Lake::factory()->create(['region_id' => $region->id, 'name' => 'Cotswold Country Park and Beach', 'slug' => 'cotswold']);

    $pages = [
        route('regions.show', $region),
        route('rivers.index', $region),
        route('rivers.show', [$region, $river]),
        route('spots.show', [$region, $river, $spot]),
        route('beaches.index', $region),
        route('beaches.show', [$region, $beach]),
        route('beaches.show', [$region, $scottish]),
        route('lakes.index', $region),
        route('lakes.show', [$region, $lake]),
    ];

    foreach ($pages as $url) {
        $metadata = pageMetadata($this->get($url));

        expect(mb_strlen($metadata['description']))->toBeLessThanOrEqual(160, "Description of {$url}: {$metadata['description']}")
            ->and($metadata['title'])->toEndWith(' - '.config('app.name'));
    }

    $longRegion = Region::factory()->create(['name' => 'East Anglia and Lincolnshire', 'slug' => 'east-anglia']);
    River::factory()->create(['region_id' => $longRegion->id]);
    BathingWater::factory()->create(['region_id' => $longRegion->id]);
    Lake::factory()->create(['region_id' => $longRegion->id]);
    $overview = pageMetadata($this->get(route('regions.show', $longRegion)));

    expect(mb_strlen(Str::beforeLast($overview['title'], ' - '.config('app.name'))))->toBeLessThanOrEqual(60, $overview['title'])
        ->and(mb_strlen($overview['description']))->toBeLessThanOrEqual(160, $overview['description']);

    $henley = Spot::factory()->create(['river_id' => River::factory()->create(['region_id' => $region->id, 'name' => 'River Thames', 'slug' => 'thames'])->id, 'name' => 'Henley-on-Thames', 'slug' => 'henley']);
    $henleyTitle = pageMetadata($this->get(route('spots.show', [$region, $henley->river, $henley])))['title'];

    foreach ([route('spots.show', [$region, $river, $spot]), route('spots.show', [$region, $henley->river, $henley])] as $url) {
        $title = pageMetadata($this->get($url))['title'];
        expect(mb_strlen(Str::beforeLast($title, ' - '.config('app.name'))))->toBeLessThanOrEqual(60, $title);
    }

    expect($henleyTitle)->toBe('Henley-on-Thames, River Thames: swimming and spills - '.config('app.name'));

    expect(pageMetadata($this->get(route('spots.show', [$region, $river, $short])))['title'])->toBe('Alston, River South Tyne: swimming, river level and spills - '.config('app.name'))
        ->and(pageMetadata($this->get(route('beaches.show', [$region, $scottish])))['description'])->toStartWith('SEPA classification and sample results');
});

it('names the place, time and source of the monitor data in one sentence', function () {
    $region = Region::factory()->create(['slug' => 'yorkshire']);
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Wharfe', 'slug' => 'wharfe']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ilkley', 'slug' => 'ilkley', 'river_metres' => 50000]);
    $this->travelTo(Carbon::parse('2026-10-16 09:30', 'Europe/London'));

    foreach ([OverflowStatus::Discharging, OverflowStatus::NotDischarging] as $index => $status) {
        $monitor = OverflowMonitor::factory()->create([
            'river_id' => $river->id, 'river_metres' => 40000 + $index, 'status' => $status,
            'company' => 'Yorkshire Water', 'source_updated_at' => Carbon::parse('2026-10-16 08:58', 'Europe/London')->utc(),
        ]);
        SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subHours(5), 'ended_at' => $status === OverflowStatus::Discharging ? null : now()->subHours(4)]);
    }

    $this->get(route('spots.show', [$region, $river, $spot]))
        ->assertOk()
        ->assertSeeText('At 08:58 BST on 16 October 2026, 1 of 2 storm overflow monitors upstream of Ilkley on the River Wharfe was discharging, with 2 spills in the previous 48 hours, according to Yorkshire Water.')
        ->assertSee('<time datetime="2026-10-16T07:58:00+00:00">', false);
});

it('says when monitors are offline or their data is old, and never counts offline ones as not discharging', function () {
    $region = Region::factory()->create(['slug' => 'north-east']);
    $beach = BathingWater::factory()->create(['region_id' => $region->id, 'name' => 'Bamburgh Castle', 'slug' => 'bamburgh', 'latitude' => 55.61, 'longitude' => -1.71]);
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Wharfe', 'slug' => 'wharfe']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ilkley', 'slug' => 'ilkley', 'river_metres' => 50000]);
    $this->travelTo(Carbon::parse('2026-10-16 09:30', 'Europe/London'));

    OverflowMonitor::factory()->create([
        'river_id' => null, 'latitude' => 55.611, 'longitude' => -1.711, 'status' => OverflowStatus::NotDischarging,
        'company' => 'Northumbrian Water', 'source_updated_at' => Carbon::parse('2026-09-24 11:05', 'Europe/London')->utc(),
    ]);

    foreach ([OverflowStatus::Offline, OverflowStatus::Offline, OverflowStatus::Discharging] as $index => $status) {
        OverflowMonitor::factory()->create([
            'river_id' => $river->id, 'river_metres' => 40000 + $index, 'status' => $status,
            'company' => 'Yorkshire Water', 'source_updated_at' => now()->subMinutes(30),
        ]);
    }

    $this->get(route('beaches.show', [$region, $beach]))
        ->assertOk()
        ->assertSeeText('At 11:05 BST on 24 September 2026, the one storm overflow monitor within 3 km of Bamburgh Castle was not discharging, with 0 spills in the previous 48 hours, according to Northumbrian Water. That is the most recent data the monitors have sent.');

    $this->get(route('spots.show', [$region, $river, $spot]))
        ->assertOk()
        ->assertSeeText('1 of 3 storm overflow monitors upstream of Ilkley on the River Wharfe was discharging and 2 were offline');

    OverflowMonitor::query()->where('river_id', $river->id)->update(['status' => OverflowStatus::Offline]);
    app(\App\Services\Conditions\ConditionsCache::class)->flush();

    $this->get(route('spots.show', [$region, $river, $spot]))
        ->assertOk()
        ->assertSeeText('all 3 storm overflow monitors upstream of Ilkley on the River Wharfe were offline, so there is no current spill data');
});

it('serves the about page ahead of region slugs, links it from every page and lists it in the sitemap', function () {
    Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);

    $this->get('/about')->assertOk()->assertSee('About the data and how it works')->assertSee('What it does not do');
    $this->get(route('home'))->assertSee(route('about'));
    $this->get(route('sitemap'))->assertSee(route('about'));
});

it('names the lowercase route as canonical, keeps search and missing pages out of the index, and lists every listing page in the sitemap', function () {
    $region = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    River::factory()->create(['region_id' => $region->id]);
    BathingWater::factory()->create(['region_id' => $region->id]);

    // MySQL also finds /Yorkshire, which then names this lowercase route as canonical; SQLite in tests is case-sensitive.
    $this->get('/yorkshire?utm_source=x')->assertOk()->assertSee('<link rel="canonical" href="'.route('regions.show', $region).'">', false);
    $this->get(route('search', ['q' => 'otley']))->assertSee('<meta name="robots" content="noindex">', false)->assertDontSee('rel="canonical"', false);
    $this->get('/nowhere-at-all')->assertNotFound()->assertSee('<meta name="robots" content="noindex">', false);

    $this->get(route('sitemap'))
        ->assertSee('<loc>'.route('rivers.index', $region).'</loc>', false)
        ->assertSee('<loc>'.route('beaches.index', $region).'</loc>', false)
        ->assertDontSee('<loc>'.route('lakes.index', $region).'</loc>', false);
});
