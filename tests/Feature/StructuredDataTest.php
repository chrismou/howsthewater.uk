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

    return collect($blocks[1])
        ->map(fn (string $json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR))
        ->each(fn (array $block) => expect($block['@context'] ?? null)->toBe('https://schema.org'))
        ->keyBy(fn (array $block) => $block['@type'] ?? 'graph')
        ->all();
}

it('describes the site and its search on the home page', function () {
    Region::factory()->create(['slug' => 'yorkshire']);

    $graph = collect(structuredData($this->get(route('home')))['graph']['@graph'])->keyBy('@type');

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
        ->and(structuredData($this->get(route('regions.show', $region)))['ItemList']['itemListElement'][0]['name'])->toBe('River Tay')
        ->and(structuredData($this->get(route('beaches.index', $region)))['ItemList']['numberOfItems'])->toBe(1);

    $this->get(route('beaches.show', [$region, $beach]))->assertSee('<a href="'.route('hubs.beaches').'" class="hover:underline">All beaches</a>', false);
    $this->get(route('regions.show', $region))->assertSee('<a href="'.route('hubs.rivers').'" class="hover:underline">All rivers</a>', false);
});
