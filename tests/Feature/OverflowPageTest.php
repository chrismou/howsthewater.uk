<?php

use App\Actions\Ingest\RefreshRegions;
use App\Models\OverflowAnnualReturn;
use App\Models\OverflowMonitor;
use App\Models\OverflowOfflinePeriod;
use App\Models\RainfallReading;
use App\Models\RainfallStation;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;

it('shows an overflow\'s status, our record, its annual returns, the places it reaches and every spill', function () {
    $this->travelTo('2026-10-17 12:00:00');
    $region = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    $river = River::factory()->withCentreline()->create(['region_id' => $region->id, 'name' => 'River Wharfe', 'slug' => 'wharfe']);
    $rain = RainfallStation::factory()->create(['label' => 'Ilkley']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ilkley', 'slug' => 'ilkley', 'river_metres' => 50000, 'rainfall_station_id' => $rain->id]);
    $monitor = OverflowMonitor::factory()->create(['external_id' => 'YWS02215', 'return_name' => 'Ellerton CSO', 'river_id' => $river->id, 'river_metres' => 47500, 'created_at' => '2026-10-13 09:00:00']);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2026-10-16 08:00:00', 'ended_at' => '2026-10-16 11:00:00']);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2026-10-14 08:00:00', 'ended_at' => '2026-10-14 08:30:00']);
    RainfallReading::factory()->create(['rainfall_station_id' => $rain->id, 'measured_at' => '2026-10-16 07:00:00', 'millimetres' => 6.4]);
    OverflowAnnualReturn::factory()->create(['overflow_monitor_id' => $monitor->id, 'year' => 2025, 'counted_spills' => 52, 'total_hours' => 330, 'monitor_uptime_percent' => 95.78, 'permit_reference' => 'YWUCD2/36']);
    OverflowAnnualReturn::factory()->create(['overflow_monitor_id' => $monitor->id, 'year' => 2024, 'counted_spills' => 84, 'total_hours' => 689, 'monitor_uptime_percent' => 99.99]);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get('/overflows/yws02215')
        ->assertOk()
        ->assertSee('<title>Ellerton CSO, River Wharfe: storm overflow spills - How\'s the Water?</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('overflows.show', $monitor).'">', false)
        ->assertDontSee('noindex')
        ->assertSee('permit')
        ->assertSee('YWUCD2/36')
        ->assertSeeInOrder(['Where do these spills come from?', 'Last 7 days', '2', '3.5 h', 'Days with a spill, last 30', '2', 'Longest spill', '3 h 0 min'])
        ->assertSee('6.4 mm of rain at the Ilkley gauge')
        ->assertSeeInOrder(['Year by year', '2025', '52', '330', '95.8%', '2024', '84', '689'])
        ->assertSeeInOrder(['Places it reaches', 'Ilkley, River Wharfe', '2.5 km downstream'])
        ->assertSeeInOrder(['Every spill recorded', 'Fri 16 Oct 2026, 09:00', '12:00', '3 h 0 min', 'Wed 14 Oct 2026, 09:00']);
});

it('pages the spill log, keeping later pages out of search results', function () {
    $region = Region::factory()->create();
    $river = River::factory()->create(['region_id' => $region->id]);
    $monitor = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 1000]);
    foreach (range(1, 55) as $daysAgo) {
        SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subDays($daysAgo), 'ended_at' => now()->subDays($daysAgo)->addHour()]);
    }

    $this->get(route('overflows.show', $monitor))->assertOk()->assertSee('Older spills')->assertSee('Page 1 of 2')->assertDontSee('noindex');
    $this->get(route('overflows.show', $monitor).'?page=2')->assertOk()->assertSee('Newer spills')->assertSee('<meta name="robots" content="noindex">', false);
    $this->get(route('overflows.show', $monitor).'?page=3')->assertNotFound();
});

it('has pages only for overflows some region shows, and keeps those without a spill in the last year or two returns out of search', function () {
    $wales = Region::factory()->create(['slug' => 'wales', 'overflow_feeds' => []]);
    $welshRiver = River::factory()->create(['region_id' => $wales->id]);
    $welsh = OverflowMonitor::factory()->create(['external_id' => 'DCWW710', 'river_id' => $welshRiver->id]);
    $unplaced = OverflowMonitor::factory()->create(['river_id' => null]);
    $region = Region::factory()->create();
    $river = River::factory()->create(['region_id' => $region->id]);
    $longAgo = ['latest_event_started_at' => now()->subYears(2), 'latest_event_ended_at' => now()->subYears(2)->addHour()];
    $quiet = OverflowMonitor::factory()->create(['external_id' => 'YWS09999', 'river_id' => $river->id, ...$longAgo]);
    // A name and an old return with spills are not enough on their own.
    $named = OverflowMonitor::factory()->create(['name' => 'Ilkley Road CSO', 'river_id' => $river->id, ...$longAgo]);
    OverflowAnnualReturn::factory()->create(['overflow_monitor_id' => $named->id, 'year' => 2023, 'counted_spills' => 12]);
    $returned = OverflowMonitor::factory()->create(['river_id' => $river->id, ...$longAgo]);
    OverflowAnnualReturn::factory()->create(['overflow_monitor_id' => $returned->id, 'year' => 2024, 'counted_spills' => 3]);
    OverflowAnnualReturn::factory()->create(['overflow_monitor_id' => $quiet->id, 'year' => 2025, 'counted_spills' => 0]);
    $recent = OverflowMonitor::factory()->create(['river_id' => $river->id]);
    $discharging = OverflowMonitor::factory()->discharging()->create(['river_id' => $river->id, 'latest_event_started_at' => null]);

    $this->get(route('overflows.show', $welsh))->assertNotFound();
    $this->get(route('overflows.show', $unplaced))->assertNotFound();
    $this->get(route('overflows.show', $quiet))
        ->assertOk()
        ->assertSee('<h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">Storm overflow YWS09999</h1>', false)
        ->assertSee('<meta name="robots" content="noindex">', false);
    $this->get(route('overflows.show', $named))->assertOk()->assertSee('<meta name="robots" content="noindex">', false);

    foreach ([$returned, $recent, $discharging] as $indexed) {
        $this->get(route('overflows.show', $indexed))->assertOk()->assertDontSee('<meta name="robots" content="noindex">', false);
    }

    $this->get(route('sitemap'))
        ->assertDontSee(route('overflows.show', $quiet))
        ->assertDontSee(route('overflows.show', $named))
        ->assertSee(route('overflows.show', $returned))
        ->assertSee(route('overflows.show', $recent))
        ->assertSee(route('overflows.show', $discharging));
});

it('ends a spill still open on a monitor that has stopped reporting at its last report, and never shows it as live', function () {
    $this->travelTo('2026-10-17 12:00:00');
    $region = Region::factory()->create();
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Wharfe']);
    $monitor = OverflowMonitor::factory()->discharging()->create(['river_id' => $river->id, 'source_updated_at' => '2026-10-15 12:00:00', 'created_at' => '2026-10-13 09:00:00']);
    SpillEvent::factory()->ongoing()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2026-10-15 10:00:00']);

    $this->get(route('overflows.show', $monitor))
        ->assertOk()
        ->assertSee('Discharging at last report')
        ->assertSee('open at last report')
        ->assertDontSee('>ongoing<', false)
        ->assertSeeInOrder(['Last 7 days', '1', '2.0 h']);
});

it('says when the rain gauge sent nothing, rather than showing a dry month', function () {
    $region = Region::factory()->create();
    $river = River::factory()->create(['region_id' => $region->id]);
    $rain = RainfallStation::factory()->create(['label' => 'Inverewe Garden']);
    Spot::factory()->create(['river_id' => $river->id, 'river_metres' => 50000, 'rainfall_station_id' => $rain->id]);
    $monitor = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 40000]);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('overflows.show', $monitor))
        ->assertOk()
        ->assertSee('The Inverewe Garden rain gauge sent no readings in this time.')
        ->assertDontSee('0.0 mm of rain');
});

it('calls a rain gauge the agency gives no name the nearby gauge, and hides the screen reader tables in a wrapper', function () {
    $region = Region::factory()->create();
    $river = River::factory()->create(['region_id' => $region->id]);
    $rain = RainfallStation::factory()->create(['label' => RainfallStation::UNNAMED_LABEL]);
    Spot::factory()->create(['river_id' => $river->id, 'river_metres' => 50000, 'rainfall_station_id' => $rain->id]);
    $monitor = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 40000]);
    app(RefreshRegions::class)->handle([$region->id]);

    // A table will not shrink to sr-only's 1px, so on the table itself it widened the page on phones.
    $this->get(route('overflows.show', $monitor))
        ->assertOk()
        ->assertSee('The nearby rain gauge sent no readings in this time.')
        ->assertDontSee('Rainfall station')
        ->assertDontSee('<table class="sr-only">', false)
        ->assertSee('<div class="sr-only">', false);
});

it('dates our record from when we first saw the overflow, works out uptime, and survives a deleted nearest place', function () {
    $this->travelTo('2026-11-20 12:00:00');
    $region = Region::factory()->create();
    $river = River::factory()->create(['region_id' => $region->id]);
    $beach = \App\Models\BathingWater::factory()->create(['region_id' => $region->id, 'latitude' => 54.28, 'longitude' => -0.40]);
    $lake = \App\Models\Lake::factory()->create(['region_id' => $region->id, 'latitude' => 54.29, 'longitude' => -0.40]);
    $monitor = OverflowMonitor::factory()->create(['external_id' => 'NWL00001', 'receiving_watercourse' => null, 'latitude' => 54.2805, 'longitude' => -0.40, 'created_at' => '2026-10-15 09:00:00']);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2026-10-08 06:00:00', 'ended_at' => '2026-10-08 07:00:00']);
    // Offline for a day of the 33 and a half since offline periods were first recorded.
    OverflowOfflinePeriod::query()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2026-11-01 00:00:00', 'ended_at' => '2026-11-02 00:00:00']);
    app(RefreshRegions::class)->handle([$region->id]);
    $beach->delete();

    $this->get(route('overflows.show', $monitor))
        ->assertOk()
        ->assertSee('<title>Storm overflow NWL00001: spills - How\'s the Water?</title>', false)
        // The 8 October spill is the feed's latest when we first saw the overflow on the 15th: listed, not the start.
        ->assertSee('Since we first saw this overflow on 15 October 2026')
        ->assertSee('Thu 8 Oct 2026, 07:00')
        ->assertSee('Any earlier spill is the latest the feed reported then.')
        ->assertSee('97.0%')
        ->assertSee($lake->name);
});

it('charts the last 12 months beside monthly rain, marking the months before we started recording', function () {
    $this->travelTo('2026-10-17 12:00:00');
    $region = Region::factory()->create();
    $river = River::factory()->create(['region_id' => $region->id]);
    $rain = RainfallStation::factory()->create(['label' => 'Ilkley']);
    Spot::factory()->create(['river_id' => $river->id, 'river_metres' => 50000, 'rainfall_station_id' => $rain->id]);
    $monitor = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 40000, 'created_at' => '2026-09-20 09:00:00']);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2026-10-02 08:00:00', 'ended_at' => '2026-10-02 12:00:00']);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2026-09-25 08:00:00', 'ended_at' => '2026-09-25 09:30:00']);
    RainfallReading::factory()->create(['rainfall_station_id' => $rain->id, 'measured_at' => '2026-10-02 07:00:00', 'millimetres' => 12.4]);
    RainfallReading::factory()->create(['rainfall_station_id' => $rain->id, 'measured_at' => '2026-09-25 07:00:00', 'millimetres' => 3.6]);
    // The feed's latest spill when we first saw the overflow, and rain from before then: neither counts.
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2026-09-18 08:00:00', 'ended_at' => '2026-09-18 11:00:00']);
    RainfallReading::factory()->create(['rainfall_station_id' => $rain->id, 'measured_at' => '2026-08-10 07:00:00', 'millimetres' => 40.0]);
    // A spill of seconds still counts.
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2026-10-09 08:00:00', 'ended_at' => '2026-10-09 08:00:20']);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('overflows.show', $monitor))
        ->assertOk()
        ->assertSee('5.5 hours of spilling over the 2 months with data')
        ->assertSee('and 16 mm of rain at the Ilkley gauge.')
        ->assertSee('No data')
        ->assertSeeInOrder(['Spills and rain per month', 'November 2025', 'no data', 'August 2026', 'no data', 'September 2026', '1', '1.5', '4 mm', 'October 2026 so far', '2', '4.0', '12 mm']);
});
