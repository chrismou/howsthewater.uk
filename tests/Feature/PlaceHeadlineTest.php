<?php

use App\Enums\FloodSeverity;
use App\Enums\OverflowStatus;
use App\Models\BathingWater;
use App\Models\FloodArea;
use App\Models\FloodWarning;
use App\Models\GaugeReading;
use App\Models\GaugeStation;
use App\Models\OverflowMonitor;
use App\Models\RainfallReading;
use App\Models\RainfallStation;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use App\Models\WeatherForecast;
use App\Services\Conditions\PlaceHeadline;
use App\Services\Conditions\SpotConditionsService;
use App\Services\Conditions\WaterBodyConditionsService;
use App\Services\Weather\PlaceWeather;

function headlineSpot(): Spot
{
    $region = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Wharfe', 'slug' => 'wharfe']);

    return Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ilkley', 'slug' => 'ilkley', 'river_metres' => 50000, 'latitude' => 53.92, 'longitude' => -1.82]);
}

function spotHeadline(Spot $spot): string
{
    $spot->load('river.region');
    $weather = app(PlaceWeather::class)->forecast($spot->latitude, $spot->longitude);

    return app(PlaceHeadline::class)->forSpot(app(SpotConditionsService::class)->build($spot), $weather)->plainText();
}

/**
 * A forecast for the spot's cell with every hour set from the given values, overridden for particular hours ahead.
 *
 * @param  array<int, array<string, mixed>>  $hoursAhead
 */
function forecastAt(Spot|BathingWater $place, array $hoursAhead = []): void
{
    $forecast = WeatherForecast::factory()->at($place->latitude, $place->longitude)->make();
    $data = $forecast->data;

    foreach ($hoursAhead as $hour => $values) {
        $data['hours'][$hour] = [...$data['hours'][$hour], ...$values];
    }

    $forecast->data = $data;
    $forecast->save();
}

it('says a quiet couple of days plainly, and qualifies it when the month was busy', function () {
    $spot = headlineSpot();
    $monitor = OverflowMonitor::factory()->create(['river_id' => $spot->river_id, 'river_metres' => 40000, 'company' => 'Yorkshire Water Services Ltd', 'source_updated_at' => now()]);

    expect(spotHeadline($spot))->toBe('No spills reported upstream in the last 48 hours.');

    foreach ([5, 9, 14] as $daysAgo) {
        SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subDays($daysAgo), 'ended_at' => now()->subDays($daysAgo)->addHour()]);
    }

    expect(spotHeadline($spot))->toBe('No spills reported upstream in the last 48 hours, but spills on 3 of the last 30 days.');
});

it('says when the last spill ended, and how many there were', function () {
    $spot = headlineSpot();
    $monitor = OverflowMonitor::factory()->create(['river_id' => $spot->river_id, 'river_metres' => 40000, 'source_updated_at' => now()]);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subHours(20), 'ended_at' => now()->subHours(18)]);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subHours(8), 'ended_at' => now()->subHours(6)]);

    expect(spotHeadline($spot))->toStartWith('Last upstream spill ended 6 hours ago, with 2 in the last 48 hours.');
});

it('describes the level in everyday words, with its trend and any flood warning', function () {
    $spot = headlineSpot();
    $station = GaugeStation::factory()->create(['river_id' => $spot->river_id, 'typical_range_low' => 0.2, 'typical_range_high' => 1.0]);
    $spot->update(['gauge_station_id' => $station->id]);
    GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => now()->subHours(3)->subMinutes(15), 'value' => 1.1]);
    GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => now()->subMinutes(15), 'value' => 1.4]);
    $area = FloodArea::factory()->hasAttached($spot)->create();
    FloodWarning::factory()->severity(FloodSeverity::FloodAlert)->create(['flood_area_id' => $area->id]);

    expect(spotHeadline($spot))->toContain('The Wharfe is higher than usual and rising. Flood alert in force.');

    GaugeReading::query()->update(['value' => 0.5]);

    expect(spotHeadline($spot))->toContain('The Wharfe is at its usual level and steady.');
});

it('notes heavy rain that has fallen, and heavy rain and thunder in the forecast', function () {
    $spot = headlineSpot();
    $rain = RainfallStation::factory()->create();
    $spot->update(['rainfall_station_id' => $rain->id]);
    RainfallReading::factory()->create(['rainfall_station_id' => $rain->id, 'measured_at' => now()->subHours(2), 'millimetres' => 12.4]);

    expect(spotHeadline($spot))->toContain('Heavy rain in the last 24 hours (12 mm): rivers can rise quickly and run faster, and overflows spill most after rain.')
        ->not->toContain('forecast');

    forecastAt($spot, [3 => ['precipitation' => 5.2], 5 => ['weather_code' => 95], 6 => ['wind_gusts' => 48.0]]);
    $from = fn (int $hours) => now()->startOfHour()->addHours($hours)->timezone('Europe/London')->format('H:i');

    expect(spotHeadline($spot))
        ->toContain('Thunderstorms forecast from around '.$from(5).'. Get out of the water if you hear thunder!')
        ->toContain('Heavy rain forecast from around '.$from(3).'.')
        // Wind does not speed a river up, so rivers get no wind note.
        ->not->toContain('Strong winds');
});

it('gives beaches their classification, pollution forecast and a strong wind note', function () {
    // In the bathing season, when pollution risk forecasts run.
    $this->travelTo(now()->setDate((int) now()->format('Y'), 7, 1));
    $region = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    $beach = BathingWater::factory()->create(['region_id' => $region->id, 'classification_year' => 2025]);
    forecastAt($beach, [2 => ['wind_gusts' => 41.6]]);

    $headline = app(PlaceHeadline::class)->forBeach(
        app(WaterBodyConditionsService::class)->forBeach($beach),
        app(PlaceWeather::class)->forecast($beach->latitude, $beach->longitude),
    )->plainText();

    expect($headline)->toStartWith('Good bathing water (2025 classification). Normal pollution risk forecast today. No monitored overflows within 3 km.')
        ->toContain('Strong winds forecast, gusting to 42 mph: open water can get rough.');
});

it('puts the headline facts in bold on the page, with the source and time below', function () {
    $spot = headlineSpot();
    OverflowMonitor::factory()->create(['river_id' => $spot->river_id, 'river_metres' => 40000, 'status' => OverflowStatus::Discharging, 'company' => 'Yorkshire Water Services Ltd', 'source_updated_at' => now()]);

    $this->get('/yorkshire/rivers/wharfe/ilkley')
        ->assertOk()
        ->assertSee('<strong class="font-semibold text-ink">1 overflow upstream is discharging now</strong>', false)
        ->assertSeeText('1 Yorkshire Water monitor upstream, data from')
        ->assertSeeText('Monitor data is not a safety check.');
});
