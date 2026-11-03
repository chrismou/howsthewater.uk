<?php

use App\Actions\Ingest\RefreshRegions;
use App\Actions\Ingest\SyncFloodWarnings;
use App\Enums\Agency;
use App\Enums\FloodSeverity;
use App\Models\FloodArea;
use App\Models\FloodWarning;
use App\Models\FloodWarningEvent;
use App\Models\GaugeReading;
use App\Models\GaugeStation;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use App\Services\Conditions\PlaceHeadline;
use App\Services\Conditions\RegionSummaries;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The Wharfe with a gauged spot at Ilkley: the gauge read `before` three hours ago and `now` just now, against a usual
 * range of 0.2 to 1.0 m, with a record of 4.21 m from Boxing Day 2015.
 *
 * @return array{0: Region, 1: River, 2: Spot, 3: GaugeStation}
 */
function gaugedRiver(float $before = 0.5, float $now = 0.5, array $river = []): array
{
    $region = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Wharfe', 'slug' => 'wharfe', 'length_metres' => 100000, ...$river]);
    $station = GaugeStation::factory()->create([
        'river_id' => $river->id, 'label' => 'Ilkley', 'river_metres' => 50000, 'typical_range_low' => 0.2, 'typical_range_high' => 1.0,
        'record_level' => 4.21, 'record_level_at' => Carbon::parse('2015-12-26 14:30:00'), 'records_from' => '1974-01-01',
    ]);
    GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => now()->subHours(3), 'value' => $before]);
    GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => now(), 'value' => $now]);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ilkley', 'slug' => 'ilkley', 'river_metres' => 52000, 'gauge_station_id' => $station->id]);

    return [$region, $river, $spot, $station];
}

function riverHeadline(Region $region, River $river): string
{
    $summary = app(RegionSummaries::class)->build($region)->rivers->firstWhere('id', $river->id);

    return app(PlaceHeadline::class)->forRiver($summary, null, $region->hasOverflowData())->plainText();
}

it('leads a river page with its flood state and levels, and lists each gauge with its record', function () {
    [$region, $river, $spot] = gaugedRiver(1.0, 1.4);
    $ouse = River::factory()->create(['region_id' => $region->id, 'name' => 'River Ouse', 'slug' => 'ouse']);
    $river->update(['flows_into_river_id' => $ouse->id]);
    River::factory()->create(['region_id' => $region->id, 'name' => 'River Washburn', 'parent_river_id' => $river->id, 'joins_at_metres' => 60000]);
    FloodArea::factory()->hasAttached($spot)->create(['label' => 'River Wharfe at Ilkley']);
    app(SyncFloodWarnings::class)->handle(collect());
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('rivers.show', [$region, $river]))
        ->assertOk()
        ->assertSee("<title>River Wharfe levels, flood alerts and spills - How's the Water?</title>", false)
        ->assertSee('100 km from source to the Ouse, with the Washburn flowing into it.')
        ->assertSee('1 swimming spot, 1 level gauge and 0 storm overflow monitors on the Wharfe and its tributaries')
        ->assertSeeInOrder(['No flood alerts or warnings in force', 'for the Wharfe.', 'The water level at Ilkley is', 'higher than usual', 'and', 'rising', '(up 0.40 m in 3 hours).'])
        ->assertSee('Level from the Ilkley gauge at')
        ->assertSeeInOrder(['Wharfe river levels', 'Ilkley gauge', '1.40 m', 'Rising, +0.40 m in 3 hours', 'Higher than usual', 'usually 0.20 to 1.00 m, record 4.21 m', 'Highest on record', '4.21 m on 26 December 2015', '(records from 1974)'])
        ->assertSee('data-sparkline-label="Ilkley gauge level over the last', false)
        ->assertSee('&quot;kind&quot;:&quot;level&quot;,&quot;state&quot;:&quot;higher&quot;', false)
        ->assertSee('Along the Wharfe, source to mouth')
        ->assertSee('Where people swim, paddle and fish on the Wharfe')
        ->assertSee('Swimming spot, 52 km from the source')
        ->assertSee('1.40 m at the Ilkley gauge, higher than usual, rising')
        ->assertSee('Level chart, overflows upstream, 30 day spill history, weather and daylight')
        ->assertDontSee('upstream monitors')
        ->assertDontSee('1 spot,');
});

it('shows the flood alerts and warnings in force, and when the last one was lifted', function () {
    [$region, $river, $spot] = gaugedRiver();
    $area = FloodArea::factory()->hasAttached($spot)->create(['label' => 'River Wharfe at Ilkley']);
    FloodWarning::factory()->create(['flood_area_id' => $area->id]);
    FloodWarningEvent::factory()->create(['flood_area_id' => $area->id, 'severity_level' => FloodSeverity::FloodWarning, 'started_at' => Carbon::parse('2026-10-12 06:00'), 'ended_at' => Carbon::parse('2026-10-14 09:00')]);
    app(RefreshRegions::class)->handle([$region->id]);

    expect(riverHeadline($region, $river))->toStartWith('Flood alert in force for River Wharfe at Ilkley. The water level at Ilkley is within its usual range and steady.');

    $this->get(route('rivers.show', [$region, $river]))
        ->assertOk()
        ->assertSee('Flood alert in force')
        ->assertSee('Flooding is possible. Be prepared.')
        ->assertSee('when the Environment Agency issues a flood alert or warning for this stretch, it is shown at the top of this page.')
        ->assertSee('The last flood warning here, for River Wharfe at Ilkley, ended on')
        ->assertSee('14 October 2026')
        ->assertSee('Flood alert in force');
});

it('says a warning was in force when last checked once no poll has confirmed it for hours', function () {
    [$region, $river, $spot] = gaugedRiver();
    $area = FloodArea::factory()->hasAttached($spot)->create(['label' => 'River Wharfe at Ilkley']);
    FloodWarning::factory()->create(['flood_area_id' => $area->id, 'checked_at' => now()->subDays(3)]);

    expect(riverHeadline($region, $river))->toStartWith('Flood alert in force when last checked 3 days ago, for River Wharfe at Ilkley; it may have changed since.');
});

it('leaves SEPA areas out while its flag is off, so a Scottish river never claims no warnings on an unread feed', function () {
    [$region, $river, $spot] = gaugedRiver();
    FloodArea::factory()->hasAttached($spot)->create(['agency' => Agency::Sepa]);
    config(['services.sepa.flood_warnings.enabled' => false]);
    app(SyncFloodWarnings::class)->handle(collect(), Agency::Sepa);
    SyncFloodWarnings::forgetCheck(Agency::Sepa);

    expect(riverHeadline($region, $river))->not->toContain('lood')
        ->and(SyncFloodWarnings::lastCheckedAt(Agency::Sepa))->toBeNull();

    $this->get(route('rivers.show', [$region, $river]))->assertOk()->assertDontSee('issues a flood alert or warning');

    config(['services.sepa.flood_warnings.enabled' => true]);

    expect(riverHeadline($region, $river))->toStartWith('Flood warnings have not been checked yet.');
});

it('says nothing about flooding where no flood area is linked, and says when the feed was last checked', function () {
    [$region, $river, $spot] = gaugedRiver();

    expect(riverHeadline($region, $river))->not->toContain('flood');

    FloodArea::factory()->hasAttached($spot)->create();

    expect(riverHeadline($region, $river))->toStartWith('Flood warnings have not been checked yet.');

    $this->travelTo(now()->subHours(7), fn () => app(SyncFloodWarnings::class)->handle(collect()));

    expect(riverHeadline($region, $river))->toContain('Flood warnings were last checked at')
        ->and(riverHeadline($region, $river))->toContain(', so this may be out of date.')
        ->and(SyncFloodWarnings::lastCheckedAt(Agency::NaturalResourcesWales))->toBeNull();
});

it('groups the gauges by where they sit against their usual range, and says which have gone silent', function () {
    [$region, $river, $spot] = gaugedRiver(0.5, 0.5);
    $doune = GaugeStation::factory()->create(['river_id' => $river->id, 'label' => 'Addingham', 'typical_range_low' => 0.2, 'typical_range_high' => 1.0]);
    GaugeReading::factory()->create(['gauge_station_id' => $doune->id, 'measured_at' => now()->subHours(3), 'value' => 1.1]);
    GaugeReading::factory()->create(['gauge_station_id' => $doune->id, 'measured_at' => now(), 'value' => 1.3]);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Addingham', 'slug' => 'addingham', 'river_metres' => 45000, 'gauge_station_id' => $doune->id]);
    $silent = GaugeStation::factory()->create(['river_id' => $river->id, 'label' => 'Otley', 'typical_range_low' => 0.2, 'typical_range_high' => 1.0]);
    GaugeReading::factory()->create(['gauge_station_id' => $silent->id, 'measured_at' => now()->subHours(9), 'value' => 0.4]);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Otley', 'slug' => 'otley', 'river_metres' => 60000, 'gauge_station_id' => $silent->id]);

    expect(riverHeadline($region, $river))
        ->toContain('The water level at Addingham is higher than usual and rising (up 0.20 m in 3 hours). The water level at Ilkley is within its usual range and steady. The Otley gauge has not reported for 9 hours.');

    $this->get(route('rivers.show', [$region, $river]))
        ->assertOk()
        ->assertSee('There are 3 gauges measuring the water level on the River Wharfe, listed source to mouth.')
        ->assertSeeInOrder(['Addingham gauge', 'Ilkley gauge', 'Otley gauge', 'No reading from this gauge for 9 hours']);
});

it('gives a gauge without a usual range its reading, and counts a gauge with no reading for two days as silent', function () {
    [$region, $river, $spot, $station] = gaugedRiver(0.5, 0.7);
    $station->update(['typical_range_low' => null, 'typical_range_high' => null]);
    $quiet = GaugeStation::factory()->create(['river_id' => $river->id, 'label' => 'Otley']);
    GaugeReading::factory()->create(['gauge_station_id' => $quiet->id, 'measured_at' => now()->subDays(3), 'value' => 0.4]);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Otley', 'slug' => 'otley', 'river_metres' => 60000, 'gauge_station_id' => $quiet->id]);

    expect(riverHeadline($region, $river))
        ->toContain('The water level at Ilkley is 0.70 m and rising (up 0.20 m in 3 hours). The Otley gauge has not reported for over 2 days.')
        ->not->toContain('usual');

    $this->get(route('rivers.show', [$region, $river]))->assertOk()->assertSee('Level from the Ilkley gauge at');
});

it('gives several silent gauges the shortest silence among them', function () {
    [$region, $river] = gaugedRiver();
    foreach ([['Addingham', 45000, 40], ['Otley', 60000, 8]] as [$name, $metres, $hoursAgo]) {
        $station = GaugeStation::factory()->create(['river_id' => $river->id, 'label' => $name]);
        GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => now()->subHours($hoursAgo), 'value' => 0.4]);
        Spot::factory()->create(['river_id' => $river->id, 'name' => $name, 'slug' => Str::slug($name), 'river_metres' => $metres, 'gauge_station_id' => $station->id]);
    }

    expect(riverHeadline($region, $river))->toContain('The Addingham and Otley gauges have not reported for at least 8 hours.');
});

it('mentions both the rising and the falling gauges in a group', function () {
    [$region, $river] = gaugedRiver(0.7, 0.5);
    $doune = GaugeStation::factory()->create(['river_id' => $river->id, 'label' => 'Addingham', 'typical_range_low' => 0.2, 'typical_range_high' => 1.0]);
    GaugeReading::factory()->create(['gauge_station_id' => $doune->id, 'measured_at' => now()->subHours(3), 'value' => 0.5]);
    GaugeReading::factory()->create(['gauge_station_id' => $doune->id, 'measured_at' => now(), 'value' => 0.8]);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Addingham', 'slug' => 'addingham', 'river_metres' => 45000, 'gauge_station_id' => $doune->id]);

    expect(riverHeadline($region, $river))
        ->toContain('Water levels at Addingham and Ilkley are within their usual range, rising at Addingham (up 0.30 m in 3 hours) and falling at Ilkley (down 0.20 m in 3 hours).');
});

it('names several gauges in one sentence when they agree', function () {
    [$region, $river] = gaugedRiver(0.5, 0.5);
    $doune = GaugeStation::factory()->create(['river_id' => $river->id, 'label' => 'Addingham', 'typical_range_low' => 0.2, 'typical_range_high' => 1.0]);
    GaugeReading::factory()->create(['gauge_station_id' => $doune->id, 'measured_at' => now()->subHours(3), 'value' => 0.6]);
    GaugeReading::factory()->create(['gauge_station_id' => $doune->id, 'measured_at' => now(), 'value' => 0.6]);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Addingham', 'slug' => 'addingham', 'river_metres' => 45000, 'gauge_station_id' => $doune->id]);

    expect(riverHeadline($region, $river))->toContain('Water levels at Addingham and Ilkley are within their usual range and steady.');
});

it('charts the spills of every overflow on the river and its tributaries over the last 30 days', function () {
    $this->travelTo('2026-11-03 12:00:00');
    [$region, $river] = gaugedRiver();
    $tributary = River::factory()->create(['region_id' => $region->id, 'parent_river_id' => $river->id]);
    $monitor = OverflowMonitor::factory()->create(['river_id' => $tributary->id, 'created_at' => now()->subDays(40), 'source_updated_at' => now()]);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subDays(2)->startOfDay()->addHours(6), 'ended_at' => now()->subDays(2)->startOfDay()->addHours(8)]);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('rivers.show', [$region, $river]))
        ->assertOk()
        ->assertSee('Storm overflow spills on the Wharfe and its tributaries, last 30 days')
        ->assertSee('Spilling on')
        ->assertSee('1 of the last 30 days')
        ->assertSee('No spills reported on the Wharfe and its tributaries in the last 48 hours');
});

it('says when no gauge is linked to a river yet, rather than showing an empty levels section', function () {
    $region = Region::factory()->create(['slug' => 'yorkshire']);
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Nidd', 'slug' => 'nidd']);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Knaresborough', 'slug' => 'knaresborough']);

    $this->get(route('rivers.show', [$region, $river]))
        ->assertOk()
        ->assertSee("No level gauge is linked to the Nidd's swimming spots yet.", false)
        ->assertSee("<title>River Nidd levels and spills - How's the Water?</title>", false)
        ->assertDontSee('Highest on record');
});

it('colours a reading above its usual range blue and marks the record on the range bar', function () {
    [$region, $river] = gaugedRiver(1.0, 1.4);
    app(RefreshRegions::class)->handle([$region->id]);

    // The band runs from the range low at 10% to the range high, 0.8 m of the 4.01 m up to the record at 92%.
    $this->get(route('rivers.show', [$region, $river]))
        ->assertOk()
        ->assertSee('class="font-mono text-3xl font-semibold tabular-nums text-accent">1.40 m', false)
        ->assertSee('class="shrink-0 font-medium text-accent">Higher than usual', false)
        ->assertSee('style="left:10%;right:74%;', false)
        ->assertSee('style="left:92%" data-record-tick', false)
        ->assertSee('style="left:35%"', false);
});

it('leaves a reading inside its usual range uncoloured and keeps the plain bar without a record', function () {
    [$region, $river, , $station] = gaugedRiver(0.5, 0.6);
    $station->update(['record_level' => null, 'record_level_at' => null]);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('rivers.show', [$region, $river]))
        ->assertOk()
        ->assertSee('class="font-mono text-3xl font-semibold tabular-nums">0.60 m', false)
        ->assertSee('class="shrink-0 font-medium text-ink">At its usual level', false)
        ->assertSee('style="left:22%;right:30%;', false)
        ->assertDontSee('data-record-tick', false)
        ->assertSee('usually 0.20 to 1.00 m</span>', false);
});
