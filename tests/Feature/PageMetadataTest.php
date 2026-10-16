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

    foreach ([route('home'), route('search'), route('about'), route('privacy'), route('terms')] as $url) {
        $metadata = pageMetadata($this->get($url));

        expect(mb_strlen(Str::beforeLast($metadata['title'], " - How's the Water?")))->toBeLessThanOrEqual(60, "Title of {$url}: {$metadata['title']}")
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
            ->and($metadata['title'])->toEndWith(" - How's the Water?");
    }

    expect(pageMetadata($this->get(route('spots.show', [$region, $river, $short])))['title'])->toBe("Alston, River South Tyne: swimming, river level and spills - How's the Water?")
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
        ->assertSee('At 08:58 BST on 16 October, 1 of 2 storm overflow monitors upstream of Ilkley on the River Wharfe was discharging, with 2 spills in the previous 48 hours, according to Yorkshire Water.');
});

it('serves the about page ahead of region slugs, links it from every page and lists it in the sitemap', function () {
    Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);

    $this->get('/about')->assertOk()->assertSee('About the data and how it works')->assertSee('What it does not do');
    $this->get(route('home'))->assertSee(route('about'));
    $this->get(route('sitemap'))->assertSee(route('about'));
});
