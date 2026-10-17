<?php

use App\Models\OverflowAnnualReturn;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Data\RiverReturnRank;
use App\Models\BathingWater;
use App\Models\Spot;
use App\Actions\Ingest\RefreshRegions;

/**
 * Yorkshire's Wharfe (with a tributary) and Aire, and Scotland's Tay, with returns for 2024 and 2025.
 *
 * @return array{0: Region, 1: River, 2: River}
 */
function riversWithReturns(): array
{
    $yorkshire = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    $wharfe = River::factory()->create(['region_id' => $yorkshire->id, 'name' => 'River Wharfe', 'slug' => 'wharfe']);
    $washburn = River::factory()->create(['region_id' => $yorkshire->id, 'name' => 'River Washburn', 'slug' => 'washburn', 'parent_river_id' => $wharfe->id]);
    $aire = River::factory()->create(['region_id' => $yorkshire->id, 'name' => 'River Aire', 'slug' => 'aire']);
    Spot::factory()->create(['river_id' => $wharfe->id, 'slug' => 'ilkley']);

    $return = function (River $river, array $years) {
        $monitor = OverflowMonitor::factory()->create(['river_id' => $river->id]);

        foreach ($years as $year => [$spills, $hours]) {
            OverflowAnnualReturn::factory()->create(['overflow_monitor_id' => $monitor->id, 'year' => $year, 'counted_spills' => $spills, 'total_hours' => $hours]);
        }
    };

    $return($wharfe, [2025 => [10, 100], 2024 => [20, 300]]);
    $return($washburn, [2025 => [5, 50]]);
    $return($aire, [2025 => [40, 600]]);
    // A monitor with no return yet.
    OverflowMonitor::factory()->create(['river_id' => $aire->id]);

    $scotland = Region::factory()->create(['name' => 'Scotland', 'slug' => 'scotland', 'overflow_feeds' => ['scottish_water']]);
    OverflowMonitor::factory()->create(['river_id' => River::factory()->create(['region_id' => $scotland->id, 'name' => 'River Tay', 'slug' => 'tay'])->id]);
    $midlands = Region::factory()->create(['name' => 'Midlands', 'slug' => 'midlands', 'overflow_feeds' => ['severn_trent']]);
    $return(River::factory()->create(['region_id' => $midlands->id, 'name' => 'River Trent', 'slug' => 'trent']), [2025 => [8, 80]]);

    return [$yorkshire, $wharfe, $aire];
}

it('compares a region\'s rivers by hours from the annual returns, tributaries included', function () {
    [$yorkshire] = riversWithReturns();

    $this->get(route('spills.region', $yorkshire))
        ->assertOk()
        ->assertSee('How Yorkshire\'s rivers compare, 2025')
        // Aire first with 600 hours from 1 of its 2 overflows; the Wharfe adds its tributary's 50 to its own 100.
        ->assertSeeInOrder(['River Aire', '2', '1 with a return', '40', '600', '600.0', 'River Wharfe', '2', '15', '150', '75.0']);
});

it('compares the regions on the combined spills page, saying where no returns are read', function () {
    riversWithReturns();

    $this->get(route('spills.index'))
        ->assertOk()
        ->assertSeeInOrder(['How the regions compare, 2025', 'Yorkshire', '55', '750', 'Midlands', '8', '80', 'Scotland', 'No annual returns read for these overflows']);
});

it('shows a river\'s years and where it stands among the region\'s rivers', function () {
    [$yorkshire, $wharfe] = riversWithReturns();

    $this->get(route('rivers.show', [$yorkshire, $wharfe]))
        ->assertOk()
        ->assertSee('In 2025 the Wharfe and its tributaries had the 2nd most hours of spilling of the 2 Yorkshire rivers with annual returns.')
        ->assertSeeInOrder(['Year by year', '2025', '2', '15', '150', '2024', '1', '20', '300']);
});

it('counts overflows near beaches in the region, leaves out returns without hours, and says joint for ties', function () {
    [$yorkshire, $wharfe, $aire] = riversWithReturns();
    // The Aire's 600 hours, matched by the Wharfe's on another overflow, make a tie.
    OverflowAnnualReturn::factory()->create(['overflow_monitor_id' => OverflowMonitor::factory()->create(['river_id' => $wharfe->id])->id, 'year' => 2025, 'counted_spills' => 3, 'total_hours' => 450]);
    // A return with no hours is not a return.
    OverflowAnnualReturn::factory()->create(['overflow_monitor_id' => OverflowMonitor::factory()->create(['river_id' => $aire->id])->id, 'year' => 2025, 'counted_spills' => 9, 'total_hours' => null]);
    BathingWater::factory()->create(['region_id' => $yorkshire->id, 'latitude' => 54.28, 'longitude' => -0.40]);
    $nearBeach = OverflowMonitor::factory()->create(['latitude' => 54.285, 'longitude' => -0.40]);
    OverflowAnnualReturn::factory()->create(['overflow_monitor_id' => $nearBeach->id, 'year' => 2025, 'counted_spills' => 7, 'total_hours' => 1000]);
    app(RefreshRegions::class)->handle([$yorkshire->id]);

    $this->get(route('rivers.show', [$yorkshire, $wharfe]))
        ->assertSee('In 2025 the Wharfe and its tributaries had the joint most hours of spilling of the 2 Yorkshire rivers with annual returns.');
    // 600 + 600 on the rivers and 1,000 near the beach; the Aire's return without hours is left out of "with a return".
    $this->get(route('spills.index'))->assertSeeInOrder(['How the regions compare', 'Yorkshire', '65', '2,200']);
    $this->get(route('spills.region', $yorkshire))->assertSeeInOrder(['River Aire', '3', '1 with a return']);
});

it('compares nothing where fewer than two rivers have returns, and says so for Scotland', function () {
    $thames = Region::factory()->create(['name' => 'Thames Valley', 'slug' => 'thames-valley', 'overflow_feeds' => ['thames_water']]);
    $river = River::factory()->create(['region_id' => $thames->id, 'name' => 'River Thames', 'slug' => 'thames']);
    OverflowAnnualReturn::factory()->create(['overflow_monitor_id' => OverflowMonitor::factory()->create(['river_id' => $river->id])->id, 'year' => 2025, 'total_hours' => 40]);
    $scotland = Region::factory()->create(['name' => 'Scotland', 'slug' => 'scotland', 'overflow_feeds' => ['scottish_water']]);
    foreach (['tay', 'spey'] as $slug) {
        OverflowMonitor::factory()->create(['river_id' => River::factory()->create(['region_id' => $scotland->id, 'slug' => $slug])->id]);
    }

    $this->get(route('rivers.show', [$thames, $river]))->assertOk()->assertSee('Year by year')->assertDontSee('most hours of spilling');
    $this->get(route('spills.region', $thames))->assertOk()->assertDontSee('id="compare"', false);
    $this->get(route('spills.region', $scotland))->assertOk()->assertDontSee('id="compare"', false)->assertSee('Scottish Water\'s annual overflow figures are not read', false);
});

it('builds links for the host serving the page, and shows a new import at once', function () {
    [$yorkshire, $wharfe] = riversWithReturns();

    $this->get('http://203.0.113.5/yorkshire/spills')->assertOk();
    // The menus are another matter (built by regions:refresh from the configured URL); the comparison's own links follow the page.
    $this->get(route('spills.region', $yorkshire))->assertSee('href="'.route('rivers.show', [$yorkshire, $wharfe]).'" class="font-medium hover:underline"', false);
    $this->get(route('rivers.show', [$yorkshire, $wharfe]))->assertSee('2nd most hours');

    OverflowAnnualReturn::factory()->create(['overflow_monitor_id' => OverflowMonitor::factory()->create(['river_id' => $wharfe->id])->id, 'year' => 2023, 'total_hours' => 5]);
    \Illuminate\Support\Facades\Cache::forever(\App\Services\Conditions\AnnualReturnTotals::GENERATION_KEY, 1);

    $this->get(route('rivers.show', [$yorkshire, $wharfe]))->assertSeeInOrder(['Year by year', '2025', '2024', '2023']);
});

it('words ranks and ordinals', function (int $position, bool $joint, string $phrase) {
    expect((new RiverReturnRank(2025, $position, 20, $joint))->phrase())->toBe($phrase);
})->with([
    [1, false, 'the most hours of spilling'],
    [2, false, 'the 2nd most hours of spilling'],
    [3, true, 'the joint 3rd most hours of spilling'],
    [11, false, 'the 11th most hours of spilling'],
    [12, false, 'the 12th most hours of spilling'],
    [13, false, 'the 13th most hours of spilling'],
    [21, false, 'the 21st most hours of spilling'],
    [22, false, 'the 22nd most hours of spilling'],
]);
