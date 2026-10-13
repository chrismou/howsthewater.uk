<?php

use App\Enums\LevelTrend;
use App\Enums\SpillRecency;
use App\Models\GaugeReading;
use App\Models\GaugeStation;
use App\Models\OverflowMonitor;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use App\Services\Conditions\ConditionsCache;
use App\Services\Conditions\SpotConditionsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

function spotWithUpstream(): array
{
    $river = River::factory()->create();
    $spot = Spot::factory()->create(['river_id' => $river->id, 'river_metres' => 50000]);

    $upstream = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 40000]);
    $downstream = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 60000]);

    return [$spot, $upstream, $downstream];
}

it('reports no data when the spot has no positioned upstream monitors', function () {
    $spot = Spot::factory()->create(['river_metres' => null]);

    $conditions = app(SpotConditionsService::class)->for($spot);

    expect($conditions->spillRecency)->toBe(SpillRecency::NoData)
        ->and($conditions->upstreamMonitors)->toBeEmpty()
        ->and($conditions->level)->toBeNull();
});

it('only counts monitors upstream of the spot', function () {
    [$spot, $upstream] = spotWithUpstream();

    $conditions = app(SpotConditionsService::class)->for($spot);

    expect($conditions->upstreamMonitors)->toHaveCount(1)
        ->and($conditions->upstreamMonitors->first()->monitor->is($upstream))->toBeTrue()
        ->and($conditions->upstreamMonitors->first()->distanceUpstreamMetres)->toBe(10000);
});

it('flags a discharging upstream monitor', function () {
    [$spot, $upstream] = spotWithUpstream();
    $upstream->update(['status' => 1]);
    SpillEvent::factory()->ongoing()->create(['overflow_monitor_id' => $upstream->id]);

    $conditions = app(SpotConditionsService::class)->for($spot);

    expect($conditions->spillRecency)->toBe(SpillRecency::DischargingNow)
        ->and($conditions->dischargingNowCount)->toBe(1)
        ->and($conditions->spillsLast48HoursCount)->toBe(1);
});

it('distinguishes spills in the last 24 and 48 hours from none', function () {
    [$spot, $upstream] = spotWithUpstream();

    $event = SpillEvent::factory()->create([
        'overflow_monitor_id' => $upstream->id,
        'started_at' => now()->subHours(40),
        'ended_at' => now()->subHours(36),
    ]);

    expect(app(SpotConditionsService::class)->build($spot)->spillRecency)->toBe(SpillRecency::Within48Hours);

    $event->update(['started_at' => now()->subHours(10), 'ended_at' => now()->subHours(8)]);

    expect(app(SpotConditionsService::class)->build($spot)->spillRecency)->toBe(SpillRecency::Within24Hours);

    $event->update(['started_at' => now()->subDays(5), 'ended_at' => now()->subDays(5)->addHour()]);

    $conditions = app(SpotConditionsService::class)->build($spot);

    expect($conditions->spillRecency)->toBe(SpillRecency::None)
        ->and($conditions->spillsLast48HoursCount)->toBe(0)
        ->and($conditions->spillsLast30DaysCount)->toBe(1);
});

it('summarises the linked gauge level and trend', function () {
    $station = GaugeStation::factory()->create(['typical_range_low' => 0.3, 'typical_range_high' => 1.0]);
    $spot = Spot::factory()->create(['gauge_station_id' => $station->id, 'river_metres' => null]);

    GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => now()->subHours(4), 'value' => 0.40]);
    GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => now()->subMinutes(15), 'value' => 0.55]);

    $level = app(SpotConditionsService::class)->for($spot)->level;

    expect($level->latestValue)->toBe(0.55)
        ->and($level->trend)->toBe(LevelTrend::Rising)
        ->and($level->changeLast3Hours)->toEqualWithDelta(0.15, 0.001)
        ->and($level->relativeToTypical)->toBe('within typical range')
        ->and($level->recentReadings)->toHaveCount(2);
});

it('reports an unknown trend when readings are stale', function () {
    $station = GaugeStation::factory()->create();
    $spot = Spot::factory()->create(['gauge_station_id' => $station->id, 'river_metres' => null]);

    GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => now()->subHours(12), 'value' => 0.40]);

    expect(app(SpotConditionsService::class)->for($spot)->level->trend)->toBe(LevelTrend::Unknown);
});

it('caches conditions until the cache is flushed', function () {
    [$spot, $upstream] = spotWithUpstream();

    $service = app(SpotConditionsService::class);

    expect($service->for($spot)->dischargingNowCount)->toBe(0);

    $upstream->update(['status' => 1]);
    SpillEvent::factory()->ongoing()->create(['overflow_monitor_id' => $upstream->id]);

    expect($service->for($spot)->dischargingNowCount)->toBe(0);

    app(ConditionsCache::class)->flush();

    expect($service->for($spot)->dischargingNowCount)->toBe(1);
});

it('survives a round trip through a serialising cache store', function () {
    config(['cache.default' => 'file']);
    Cache::store('file')->flush();

    [$spot, $upstream] = spotWithUpstream();
    $upstream->update(['status' => 1]);
    SpillEvent::factory()->ongoing()->create(['overflow_monitor_id' => $upstream->id]);
    $station = GaugeStation::factory()->create(['river_id' => $spot->river_id]);
    $spot->update(['gauge_station_id' => $station->id]);
    GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => now()->subMinutes(15), 'value' => 0.55]);

    $service = app(SpotConditionsService::class);
    $service->for($spot);

    $cached = $service->for($spot);

    expect($cached->spillRecency)->toBe(SpillRecency::DischargingNow)
        ->and($cached->upstreamMonitors->first()->monitor->is($upstream))->toBeTrue()
        ->and($cached->upstreamMonitors->first()->monitor->river->is($spot->river))->toBeTrue()
        ->and($cached->recentSpillEvents->first()->started_at)->toBeInstanceOf(Carbon::class)
        ->and($cached->level->latestValue)->toBe(0.55)
        ->and($cached->level->latestAt)->toBeInstanceOf(Carbon::class)
        ->and($cached->dailyHistory)->toHaveCount(30);

    Cache::store('file')->flush();
});
