<?php

use App\Actions\Ingest\RefreshRegions;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use App\Services\Conditions\NavigationMenus;

it('lists a river from another region where it runs through, with its spots there, linking to its one page and leaving it out of the figures', function () {
    $thamesValley = Region::factory()->create(['name' => 'Thames Valley', 'slug' => 'thames-valley']);
    $london = Region::factory()->create(['name' => 'London', 'slug' => 'london']);
    $thames = River::factory()->create(['region_id' => $thamesValley->id, 'name' => 'River Thames', 'slug' => 'thames', 'also_runs_through' => ['london' => ['teddington-lock']]]);
    Spot::factory()->create(['river_id' => $thames->id, 'name' => 'Lechlade', 'slug' => 'lechlade', 'river_metres' => 30000]);
    Spot::factory()->create(['river_id' => $thames->id, 'name' => 'Teddington Lock', 'slug' => 'teddington-lock', 'river_metres' => 250000]);
    OverflowMonitor::factory()->count(3)->create(['river_id' => $thames->id, 'river_metres' => 40000]);
    $lea = River::factory()->create(['region_id' => $london->id, 'name' => 'River Lea', 'slug' => 'lea']);
    Spot::factory()->create(['river_id' => $lea->id, 'name' => 'Hackney Marshes', 'slug' => 'hackney-marshes', 'river_metres' => 80000]);
    OverflowMonitor::factory()->create(['river_id' => $lea->id, 'river_metres' => 70000]);
    app(RefreshRegions::class)->handle([$thamesValley->id, $london->id]);

    $this->get(route('rivers.index', $london))
        ->assertOk()
        ->assertSee('1 river and 1 spot, listed source to mouth.')
        ->assertSeeInOrder(['Also through London', 'River Thames runs through London too. Its page is listed under the Thames Valley, so the figures above leave it out. Shown here are its spots in London.'])
        ->assertSee(route('rivers.show', [$thamesValley, $thames]), false)
        ->assertSee(route('spots.show', [$thamesValley, $thames, 'teddington-lock']), false)
        ->assertDontSee(route('spots.show', [$thamesValley, $thames, 'lechlade']), false)
        ->assertSee('All 2 Thames spots')
        ->assertDontSee(route('rivers.show', [$london, 'thames']), false);

    $this->getJson(route('api.v1.regions.show', $london))->assertOk()->assertJsonPath('data.monitor_count', 1);

    $this->get(route('regions.show', $london))
        ->assertOk()
        ->assertSee('also runs through London; its page is under the Thames Valley.')
        ->assertSee(route('rivers.show', [$thamesValley, $thames]), false);

    // The river's own region lists it once, as its own, with all its spots.
    $this->get(route('rivers.index', $thamesValley))->assertOk()->assertDontSee('so the figures above leave')->assertSee(route('spots.show', [$thamesValley, $thames, 'lechlade']), false);
    $this->get(route('regions.show', $thamesValley))->assertOk()->assertDontSee('also runs through');

    $londonMenu = collect(app(NavigationMenus::class)->build()['rivers'])->firstWhere('slug', 'london');
    expect($londonMenu['groups']['Also through London'])->toBe([['name' => 'River Thames', 'url' => route('rivers.show', [$thamesValley, $thames])]]);
});

it('names several rivers running through a region, and the regions they are listed under, once each', function () {
    $london = Region::factory()->create(['name' => 'London', 'slug' => 'london']);
    River::factory()->create(['region_id' => $london->id, 'slug' => 'lea']);
    foreach ([['Thames Valley', 'thames-valley', 'River Thames', 'thames'], ['South East', 'south-east', 'River Darent', 'darent']] as [$name, $slug, $river, $riverSlug]) {
        $home = Region::factory()->create(['name' => $name, 'slug' => $slug]);
        River::factory()->create(['region_id' => $home->id, 'name' => $river, 'slug' => $riverSlug, 'also_runs_through' => ['london' => []]]);
    }
    app(RefreshRegions::class)->handle(Region::query()->pluck('id')->all());

    $this->get(route('rivers.index', $london))
        ->assertOk()
        ->assertSee('River Darent and River Thames run through London too. Their pages are listed under the South East and the Thames Valley, so the figures above leave them out.');
    $this->get(route('regions.show', $london))
        ->assertOk()
        ->assertSeeInOrder(['River Darent</a> also runs through London; its page is under the South East.', 'River Thames</a> also runs through London; its page is under the Thames Valley.'], false);
});

it('redirects a guessed address for a river under a region it runs through to its own page, and 404s anything else', function () {
    $thamesValley = Region::factory()->create(['name' => 'Thames Valley', 'slug' => 'thames-valley']);
    $london = Region::factory()->create(['name' => 'London', 'slug' => 'london']);
    $scotland = Region::factory()->create(['name' => 'Scotland', 'slug' => 'scotland']);
    $thames = River::factory()->create(['region_id' => $thamesValley->id, 'slug' => 'thames', 'also_runs_through' => ['london' => ['teddington-lock']]]);
    $teddington = Spot::factory()->create(['river_id' => $thames->id, 'slug' => 'teddington-lock']);
    $tributary = River::factory()->create(['region_id' => $thamesValley->id, 'parent_river_id' => $thames->id, 'slug' => 'wey', 'also_runs_through' => ['london' => []]]);

    $this->get('/london/rivers/thames')->assertRedirect(route('rivers.show', [$thamesValley, $thames]))->assertStatus(301);
    $this->get('/london/rivers/thames/teddington-lock')->assertRedirect(route('spots.show', [$thamesValley, $thames, $teddington]))->assertStatus(301);
    $this->get('/london/thames')->assertRedirect(route('rivers.show', [$thamesValley, $thames]))->assertStatus(301);

    $this->get('/london/rivers/thames/nowhere')->assertNotFound();
    $this->get('/london/rivers/wey')->assertNotFound();
    $this->get('/scotland/rivers/thames')->assertNotFound();
    $this->get('/nowhere/rivers/thames')->assertNotFound();
    $this->get('/thames-valley/rivers/thames/nowhere')->assertNotFound();
});
