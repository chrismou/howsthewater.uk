<?php

use App\Data\RegionSummary;
use App\Enums\SpillRecency;
use App\Models\GaugeReading;
use App\Models\GaugeStation;
use App\Models\OverflowMonitor;
use App\Models\RainfallReading;
use App\Models\RainfallStation;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use App\Services\Conditions\RegionSummaries;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

function yorkshireWithTwoRivers(): array
{
    $region = Region::factory()->create(['slug' => 'yorkshire', 'name' => 'Yorkshire']);
    $wharfe = River::factory()->create(['region_id' => $region->id, 'slug' => 'wharfe', 'name' => 'River Wharfe', 'catchment' => 'Yorkshire Ouse', 'length_metres' => 100000]);
    $esk = River::factory()->create(['region_id' => $region->id, 'slug' => 'esk', 'name' => 'River Esk', 'catchment' => 'Esk and Coast', 'length_metres' => 50000]);

    $station = GaugeStation::factory()->create(['river_id' => $wharfe->id, 'label' => 'Otley', 'typical_range_low' => 0.2, 'typical_range_high' => 1.0]);
    GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => now()->subMinutes(15), 'value' => 1.4]);
    $rain = RainfallStation::factory()->create();
    RainfallReading::factory()->create(['rainfall_station_id' => $rain->id, 'measured_at' => now()->subMinutes(15), 'millimetres' => 4.5]);

    $otley = Spot::factory()->create(['river_id' => $wharfe->id, 'name' => 'Otley', 'slug' => 'otley', 'river_metres' => 60000, 'gauge_station_id' => $station->id, 'rainfall_station_id' => $rain->id]);
    Spot::factory()->create(['river_id' => $wharfe->id, 'name' => 'Ilkley', 'slug' => 'ilkley', 'river_metres' => 50000]);
    Spot::factory()->create(['river_id' => $esk->id, 'name' => 'Whitby', 'slug' => 'whitby', 'river_metres' => 48000]);

    $monitor = OverflowMonitor::factory()->discharging()->create(['river_id' => $wharfe->id, 'river_metres' => 55000]);
    SpillEvent::factory()->ongoing()->create(['overflow_monitor_id' => $monitor->id]);
    OverflowMonitor::factory()->offline()->create(['river_id' => $esk->id, 'river_metres' => 10000]);

    return [$region, $wharfe, $esk, $otley];
}

it('summarises a region from its spot conditions', function () {
    [$region] = yorkshireWithTwoRivers();

    $summary = app(RegionSummaries::class)->build($region);

    expect($summary->rivers->pluck('slug')->all())->toBe(['wharfe', 'esk'])
        ->and($summary->spots())->toHaveCount(3)
        ->and($summary->monitorCount)->toBe(2)
        ->and($summary->offlineMonitorCount)->toBe(1)
        ->and($summary->dischargingNowCount())->toBe(1)
        ->and($summary->spotsDischargingNow()->pluck('name')->all())->toBe(['Otley'])
        ->and($summary->spotsWithSpillLast24HoursCount())->toBe(1)
        ->and($summary->spotsAboveTypicalRange()->pluck('name')->all())->toBe(['Otley'])
        ->and($summary->wettestSpots()->first()->rainfallLast24HoursMillimetres)->toBe(4.5)
        ->and($summary->riversByCatchment()->keys()->all())->toBe(['Yorkshire Ouse', 'Esk and Coast']);

    $otley = $summary->spots()->firstWhere('slug', 'otley');

    expect($otley->spillRecency)->toBe(SpillRecency::DischargingNow)
        ->and($otley->levelValue)->toBe(1.4)
        ->and($otley->monitorCount)->toBe(1)
        ->and($otley->overflowDataIsStale())->toBeFalse();
});

it('serves the cached summary until it is refreshed', function () {
    [$region, $wharfe] = yorkshireWithTwoRivers();
    $summaries = app(RegionSummaries::class);

    expect($summaries->for($region)->spots())->toHaveCount(3);

    Spot::factory()->create(['river_id' => $wharfe->id, 'river_metres' => 70000]);

    expect($summaries->for($region)->spots())->toHaveCount(3)
        ->and($summaries->refresh($region)->spots())->toHaveCount(4)
        ->and($summaries->for($region))->toBeInstanceOf(RegionSummary::class);
});

it('survives a round trip through a serialising cache store', function () {
    config(['cache.default' => 'file']);
    Cache::store('file')->flush();

    [$region] = yorkshireWithTwoRivers();
    $summaries = app(RegionSummaries::class);
    $summaries->refresh($region);

    $cached = $summaries->for($region);

    expect($cached->spotsDischargingNow()->first()->name)->toBe('Otley')
        ->and($cached->spots()->firstWhere('slug', 'otley')->levelAt)->toBeInstanceOf(Carbon::class);

    Cache::store('file')->flush();
});
