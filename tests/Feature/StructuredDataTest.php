<?php

use App\Enums\BathingWaterKind;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use Illuminate\Testing\TestResponse;

/**
 * Every JSON-LD block on the page, decoded, keyed by its type.
 *
 * @return array<string, array<string, mixed>>
 */
function structuredData(TestResponse $response): array
{
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', (string) $response->assertOk()->getContent(), $blocks);

    // One graph per page, so every node can refer to the others by @id.
    expect($blocks[1])->toHaveCount(1);
    $graph = json_decode($blocks[1][0], true, flags: JSON_THROW_ON_ERROR);
    expect($graph['@context'])->toBe('https://schema.org');

    return collect($graph['@graph'])->keyBy('@type')->all();
}

it('describes the site and its search on the home page', function () {
    Region::factory()->create(['slug' => 'yorkshire']);

    $graph = structuredData($this->get(route('home')));

    expect($graph['WebSite']['name'])->toBe(config('app.name'))
        ->and($graph['WebSite']['potentialAction']['target']['urlTemplate'])->toBe(route('search').'?q={search_term_string}')
        ->and($graph['WebSite']['publisher']['@id'])->toBe($graph['Organization']['@id']);
});

it('describes spots, rivers, beaches and lakes as places with their coordinates', function () {
    $region = Region::factory()->create(['name' => 'Scotland', 'slug' => 'scotland']);
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Tay', 'slug' => 'tay']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Dunkeld', 'slug' => 'dunkeld', 'latitude' => 56.5652, 'longitude' => -3.5866]);
    $beach = BathingWater::factory()->create(['region_id' => $region->id, 'name' => 'Millport Bay', 'slug' => 'millport-bay', 'district' => 'North Ayrshire']);
    $lake = Lake::factory()->create(['region_id' => $region->id, 'name' => 'Loch Lomond', 'slug' => 'loch-lomond']);
    BathingWater::factory()->kind(BathingWaterKind::Lake)->create(['region_id' => $region->id, 'lake_id' => $lake->id]);

    $place = structuredData($this->get(route('spots.show', [$region, $river, $spot])))['Place'];
    expect($place['name'])->toBe('Dunkeld, River Tay')
        ->and($place['geo'])->toBe(['@type' => 'GeoCoordinates', 'latitude' => 56.5652, 'longitude' => -3.5866])
        ->and($place['containedInPlace']['@type'])->toBe('RiverBodyOfWater')
        ->and($place['containedInPlace']['containedInPlace']['url'])->toBe(route('regions.show', $region));

    expect(structuredData($this->get(route('rivers.show', [$region, $river]))))->toHaveKey('RiverBodyOfWater');

    $beachData = structuredData($this->get(route('beaches.show', [$region, $beach])))['Beach'];
    expect($beachData['address']['addressRegion'])->toBe('North Ayrshire')
        ->and($beachData)->not->toHaveKeys(['aggregateRating', 'review']);

    expect(structuredData($this->get(route('lakes.show', [$region, $lake]))))->toHaveKey('LakeBodyOfWater');
});

it('lists the places on listing pages, and starts the visible trail at the matching hub', function () {
    $region = Region::factory()->create(['name' => 'Scotland', 'slug' => 'scotland']);
    River::factory()->create(['region_id' => $region->id, 'name' => 'River Tay', 'slug' => 'tay']);
    $beach = BathingWater::factory()->create(['region_id' => $region->id, 'name' => 'Millport Bay', 'slug' => 'millport-bay']);

    expect(structuredData($this->get(route('hubs.beaches')))['ItemList']['itemListElement'][0])->toMatchArray(['name' => 'Scotland', 'url' => route('beaches.index', $region)])
        ->and(structuredData($this->get(route('rivers.index', $region)))['ItemList']['itemListElement'][0]['name'])->toBe('River Tay')
        ->and(structuredData($this->get(route('beaches.index', $region)))['ItemList']['numberOfItems'])->toBe(1);

    $this->get(route('beaches.show', [$region, $beach]))->assertSee('<a href="'.route('hubs.beaches').'" class="inline-block py-1.5 hover:underline">All beaches</a>', false);
    $this->get(route('rivers.index', $region))->assertSee('<a href="'.route('hubs.rivers').'" class="inline-block py-1.5 hover:underline">All rivers</a>', false);
});

it('joins each page into one graph whose WebPage points at its place, trail and date, with the visible trail matching', function () {
    $region = Region::factory()->create(['name' => 'Scotland', 'slug' => 'scotland']);
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Tay', 'slug' => 'tay']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Dunkeld', 'slug' => 'dunkeld']);
    $url = route('spots.show', [$region, $river, $spot]);

    $response = $this->get($url);
    $graph = structuredData($response);

    expect($graph['WebPage']['@id'])->toBe($url.'#webpage')
        ->and($graph['WebPage']['mainEntity']['@id'])->toBe($url.'#place')
        ->and($graph['WebPage']['breadcrumb']['@id'])->toBe($graph['BreadcrumbList']['@id'])
        ->and($graph['WebPage']['isPartOf']['@id'])->toBe($graph['WebSite']['@id'])
        ->and($graph['Place']['containedInPlace']['@id'])->toBe(route('rivers.show', [$region, $river]).'#river');

    $trail = collect($graph['BreadcrumbList']['itemListElement'])->pluck('name')->all();
    expect($trail)->toBe(['All rivers', 'Scotland', 'Rivers', 'River Tay', 'Dunkeld']);
    $response->assertSeeInOrder(['aria-label="Breadcrumb"', 'All rivers', 'Scotland', 'Rivers', 'River Tay', '</nav>'], false);

    expect(structuredData($this->get(route('about'))))->toHaveKey('AboutPage');
    $this->get(route('search', ['q' => 'x']))->assertDontSee('application/ld+json', false);
});
