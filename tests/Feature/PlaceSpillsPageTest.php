<?php

use App\Actions\Ingest\RefreshRegions;
use App\Models\BathingWater;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;

it('shows every spill upstream of a spot, counting hours when overflows spill together once', function () {
    $this->travelTo('2026-10-17 12:00:00');
    $region = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Wharfe', 'slug' => 'wharfe']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ilkley', 'slug' => 'ilkley', 'river_metres' => 50000]);
    $near = OverflowMonitor::factory()->create(['return_name' => 'Addingham CSO', 'river_id' => $river->id, 'river_metres' => 45000, 'created_at' => '2026-10-13 09:00:00']);
    $far = OverflowMonitor::factory()->create(['return_name' => 'Bolton Abbey CSO', 'river_id' => $river->id, 'river_metres' => 30000, 'created_at' => '2026-10-13 09:00:00']);
    // 08:00-11:00 and 10:00-12:00 overlap: three hours of spilling then the last hour, four in all, not five.
    SpillEvent::factory()->create(['overflow_monitor_id' => $near->id, 'started_at' => '2026-10-16 08:00:00', 'ended_at' => '2026-10-16 11:00:00']);
    SpillEvent::factory()->create(['overflow_monitor_id' => $far->id, 'started_at' => '2026-10-16 10:00:00', 'ended_at' => '2026-10-16 12:00:00']);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('spots.spills', [$region, $river, $spot]))
        ->assertOk()
        ->assertSee('<title>Ilkley, River Wharfe: spills upstream - How\'s the Water?</title>', false)
        ->assertSee('Spills upstream of Ilkley')
        ->assertSee('since we started recording them on 13 October 2026')
        ->assertSeeInOrder(['Last 7 days', '4.0 h', '2 spills'])
        ->assertSeeInOrder(['Busiest overflows upstream, since 13 Oct', 'Addingham CSO', '5.0 km', '1', '3.0', 'Bolton Abbey CSO', '20.0 km', '1', '2.0'])
        ->assertSeeInOrder(['Every spill recorded', 'Fri 16 Oct, 11:00', 'Bolton Abbey CSO', 'Fri 16 Oct, 09:00', 'Addingham CSO'])
        ->assertSee(route('overflows.show', $near), false);

    // The spot page lists the latest spills, links each overflow and the full history.
    $this->get(route('spots.show', [$region, $river, $spot]))
        ->assertSee(route('spots.spills', [$region, $river, $spot]), false)
        ->assertSeeInOrder(['Latest spills upstream', 'Bolton Abbey CSO', 'Addingham CSO'])
        ->assertSee(route('overflows.show', $far), false);
});

it('shows the spills near a beach in nearby wording, and links them from the beach page', function () {
    $region = Region::factory()->create(['slug' => 'yorkshire']);
    $beach = BathingWater::factory()->create(['region_id' => $region->id, 'name' => 'Runswick Bay', 'slug' => 'runswick-bay', 'latitude' => 54.28, 'longitude' => -0.40]);
    $monitor = OverflowMonitor::factory()->create(['return_name' => 'Runswick CSO', 'latitude' => 54.285, 'longitude' => -0.40, 'created_at' => now()->subDays(2)]);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subHours(5), 'ended_at' => now()->subHours(4)]);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('beaches.spills', [$region, $beach]))
        ->assertOk()
        ->assertSee('Spills near Runswick Bay')
        ->assertSee('within 3 km of Runswick Bay')
        ->assertSee('Busiest overflows nearby')
        ->assertDontSee('Busiest overflows upstream')
        ->assertDontSee('Spills upstream');

    $this->get(route('beaches.show', [$region, $beach]))
        ->assertSee(route('beaches.spills', [$region, $beach]), false)
        ->assertSeeInOrder(['Latest spills nearby', 'Runswick CSO']);
});

it('has no spills page where we do not read the overflow feed, nor for unpublished spots, nor past the last page', function () {
    $wales = Region::factory()->create(['slug' => 'wales', 'overflow_feeds' => []]);
    $taff = River::factory()->create(['region_id' => $wales->id, 'slug' => 'taff']);
    $welsh = Spot::factory()->create(['river_id' => $taff->id, 'slug' => 'pontypridd']);
    $region = Region::factory()->create(['slug' => 'yorkshire']);
    $river = River::factory()->create(['region_id' => $region->id, 'slug' => 'wharfe']);
    $hidden = Spot::factory()->unpublished()->create(['river_id' => $river->id, 'slug' => 'hidden']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'slug' => 'ilkley', 'river_metres' => 50000]);

    $this->get(route('spots.spills', [$wales, $taff, $welsh]))->assertNotFound();
    $this->get(route('spots.spills', [$region, $river, $hidden]))->assertNotFound();
    $this->get(route('spots.spills', [$region, $river, $spot]))->assertOk()->assertSee('No storm overflow monitors have been matched upstream');
    $this->get(route('spots.spills', [$region, $river, $spot]).'?page=2')->assertNotFound();
});

it('dates the record from when the overflows were first seen, keeps months before it out, and warns when the data is out of date', function () {
    $this->travelTo('2026-10-17 12:00:00');
    $region = Region::factory()->create(['slug' => 'yorkshire']);
    $river = River::factory()->create(['region_id' => $region->id, 'slug' => 'ouse']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Goole', 'slug' => 'goole', 'river_metres' => 50000]);
    $monitor = OverflowMonitor::factory()->discharging()->create(['river_id' => $river->id, 'river_metres' => 40000, 'created_at' => '2026-10-13 09:00:00', 'source_updated_at' => '2026-10-16 12:00:00']);
    // The feed's latest spill when we first saw the overflow, months before.
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2026-08-02 10:00:00', 'ended_at' => '2026-08-02 12:00:00']);
    // Still open, but the monitor last reported a day ago.
    SpillEvent::factory()->ongoing()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2026-10-16 10:00:00']);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('spots.spills', [$region, $river, $spot]))
        ->assertOk()
        ->assertSee('since we started recording them on 13 October 2026')
        ->assertSee('Monitor data is out of date.')
        ->assertSee('October 2026 so far')
        ->assertDontSee('August 2026')
        ->assertSeeInOrder(['Last 7 days', '2.0 h', '1 spill'])
        ->assertSee('open at last report')
        ->assertSee('Sun 2 Aug, 11:00');
});

it('keeps a place with no overflows out of search and unlinked, and its lake page links the nearby history', function () {
    $region = Region::factory()->create(['slug' => 'yorkshire']);
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Wharfe', 'slug' => 'wharfe']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Kettlewell', 'slug' => 'kettlewell', 'river_metres' => 5000]);
    $lake = \App\Models\Lake::factory()->create(['region_id' => $region->id, 'name' => 'Semerwater', 'slug' => 'semerwater', 'latitude' => 54.28, 'longitude' => -2.12, 'rainfall_station_id' => null]);
    OverflowMonitor::factory()->create(['return_name' => 'Bainbridge CSO', 'latitude' => 54.285, 'longitude' => -2.12]);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('spots.spills', [$region, $river, $spot]))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('No storm overflow monitors have been matched upstream of Kettlewell, River Wharfe yet.')
        ->assertDontSee('0 storm overflows');
    $this->get(route('spots.show', [$region, $river, $spot]))->assertDontSee(route('spots.spills', [$region, $river, $spot]), false);

    $this->get(route('lakes.show', [$region, $lake]))->assertSee(route('lakes.spills', [$region, $lake]), false);
    $this->get(route('lakes.spills', [$region, $lake]))
        ->assertOk()
        ->assertSee('Spills near Semerwater')
        ->assertSee('Every spill from the 1 storm overflow within 3 km of Semerwater')
        ->assertDontSee('mm of rain');
    $this->get(route('sitemap'))
        ->assertSee(route('lakes.spills', [$region, $lake]), false)
        ->assertDontSee(route('spots.spills', [$region, $river, $spot]), false);
});

it('finds no beach from another region, and shows no nearby overflows where we do not read the feed', function () {
    $yorkshire = Region::factory()->create(['slug' => 'yorkshire']);
    $wales = Region::factory()->create(['slug' => 'wales', 'overflow_feeds' => []]);
    $welsh = BathingWater::factory()->create(['region_id' => $wales->id, 'slug' => 'tenby', 'latitude' => 51.67, 'longitude' => -4.70]);
    $monitor = OverflowMonitor::factory()->create(['external_id' => 'DCWW04', 'company' => 'Dŵr Cymru Welsh Water', 'latitude' => 51.672, 'longitude' => -4.70]);
    app(RefreshRegions::class)->handle([$wales->id]);

    $this->get(route('beaches.spills', [$yorkshire, $welsh]))->assertNotFound();
    $this->get(route('beaches.spills', [$wales, $welsh]))->assertNotFound();
    $this->get(route('beaches.show', [$wales, $welsh]))->assertOk()->assertDontSee('DCWW04')->assertDontSee(route('overflows.show', $monitor), false);
});
