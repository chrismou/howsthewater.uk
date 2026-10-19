<?php

use App\Actions\Ingest\RefreshRegions;
use App\Models\GaugeReading;
use App\Models\GaugeStation;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;

/**
 * A spot on its own river whose gauge read `before` three hours ago and `now` just now, with a usual range of 0.2 to 1.0 m.
 */
function levelSpot(Region $region, string $riverName, string $spotName, float $before, float $now): Spot
{
    $river = River::factory()->create(['region_id' => $region->id, 'name' => $riverName, 'slug' => Str::slug($riverName)]);
    $station = GaugeStation::factory()->create(['river_id' => $river->id, 'label' => $spotName, 'typical_range_low' => 0.2, 'typical_range_high' => 1.0]);
    GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => now()->subHours(3), 'value' => $before]);
    GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => now(), 'value' => $now]);

    return Spot::factory()->create(['river_id' => $river->id, 'name' => $spotName, 'slug' => Str::slug($spotName), 'gauge_station_id' => $station->id]);
}

it('lists the rivers higher than usual and those rising, each at its spot, with a map of both', function () {
    $region = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    $high = levelSpot($region, 'River Wharfe', 'Ilkley', 1.40, 1.40);
    $rising = levelSpot($region, 'River Aire', 'Kirkstall', 0.50, 0.70);
    levelSpot($region, 'River Nidd', 'Knaresborough', 0.50, 0.50);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('levels.region', $region))
        ->assertOk()
        ->assertSee('<title>River levels in Yorkshire now: higher and rising - How\'s the Water?</title>', false)
        ->assertSeeInOrder(['id="higher"', 'Higher than usual', 'River Wharfe', 'Ilkley', '1.40 m', 'higher than usual', 'id="rising"', 'Rising now', 'River Aire', 'Kirkstall', '0.70 m', 'rising'], false)
        ->assertSee(route('rivers.show', [$region, $high->river]), false)
        ->assertSee(route('spots.show', [$region, $high->river, $high]), false)
        ->assertSee(route('spots.show', [$region, $rising->river, $rising]), false)
        ->assertSee('&quot;kind&quot;:&quot;level&quot;,&quot;state&quot;:&quot;higher&quot;', false)
        ->assertSee('&quot;state&quot;:&quot;rising&quot;', false)
        ->assertDontSee('Knaresborough');

    // Every region's page names the region on each row and links each region's own page.
    $this->get(route('levels.index'))
        ->assertOk()
        ->assertSee('<title>River levels now: higher than usual and rising - How\'s the Water?</title>', false)
        ->assertSeeInOrder(['River Wharfe', 'Yorkshire · Ilkley gauge'], false)
        ->assertSee(route('levels.region', $region), false);
});

it('says when no river is higher than usual or rising, and draws no map', function () {
    $region = Region::factory()->create(['slug' => 'yorkshire']);
    levelSpot($region, 'River Nidd', 'Knaresborough', 0.50, 0.50);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('levels.region', $region))
        ->assertOk()
        ->assertSee('No river is higher than usual right now.')
        ->assertSee('No river is rising right now.')
        ->assertDontSee('data-map ', false);
});

it('shows the first 25 rivers and opens the rest in place', function () {
    $region = Region::factory()->create(['slug' => 'yorkshire']);

    foreach (range(1, 27) as $number) {
        levelSpot($region, 'River '.sprintf('%02d', $number), 'Spot '.$number, 1.40, 1.40);
    }

    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('levels.region', $region))
        ->assertOk()
        ->assertSeeInOrder(['River 25', '<details', 'Show the other 2', 'River 26', 'River 27', '</details>'], false);
});

it('links the level and spill figures to the pages listing them, and lists them in the sitemap', function () {
    $region = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    Region::factory()->create(['name' => 'Scotland', 'slug' => 'scotland', 'overflow_feeds' => []]);
    levelSpot($region, 'River Wharfe', 'Ilkley', 1.40, 1.40);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('home'))
        ->assertSee(route('levels.index').'#higher', false)
        ->assertSee(route('levels.index').'#rising', false)
        ->assertSee(route('spills.index').'#spots', false);

    foreach (['regions.show', 'rivers.index'] as $page) {
        $this->get(route($page, $region))
            ->assertSee(route('levels.region', $region).'#higher', false)
            ->assertSee(route('spills.region', $region).'#discharging', false)
            ->assertSee('Spills in Yorkshire now</a>', false)
            ->assertSee('River levels in Yorkshire now</a>', false);
    }

    $this->get(route('regions.show', 'scotland'))
        ->assertSee('River levels in Scotland now</a>', false)
        ->assertDontSee('Spills in Scotland now');

    // The footer links each region's levels, and its spills where we read its overflow data, each naming the region.
    $this->get(route('home'))
        ->assertSee(route('levels.region', 'scotland'), false)
        ->assertSee('Levels<span class="sr-only"> in Scotland</span>', false)
        ->assertSee('Spills<span class="sr-only"> in Yorkshire</span>', false)
        ->assertDontSee(route('spills.region', 'scotland'), false);

    $this->get(route('sitemap'))
        ->assertSee('<loc>'.route('levels.index').'</loc>', false)
        ->assertSee('<loc>'.route('levels.region', $region).'</loc>', false);
});
