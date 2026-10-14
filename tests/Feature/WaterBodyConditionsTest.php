<?php

use App\Enums\OverflowStatus;
use App\Enums\PollutionRisk;
use App\Enums\SpillRecency;
use App\Models\BathingWater;
use App\Models\BathingWaterSample;
use App\Models\Lake;
use App\Models\OverflowMonitor;
use App\Models\SpillEvent;
use App\Models\Spot;
use App\Services\Conditions\SpotConditionsService;
use App\Services\Conditions\WaterBodyConditionsService;

it('lists overflows within 3 km of a beach, nearest first', function () {
    $beach = BathingWater::factory()->create(['latitude' => 54.28, 'longitude' => -0.40]);
    $near = OverflowMonitor::factory()->discharging()->create(['latitude' => 54.29, 'longitude' => -0.40]);
    $nearer = OverflowMonitor::factory()->create(['latitude' => 54.281, 'longitude' => -0.40, 'status' => OverflowStatus::NotDischarging]);
    OverflowMonitor::factory()->discharging()->create(['latitude' => 54.40, 'longitude' => -0.40]);
    SpillEvent::factory()->ongoing()->create(['overflow_monitor_id' => $near->id]);

    $overflows = app(WaterBodyConditionsService::class)->buildBeach($beach)->overflows;

    expect($overflows->monitors->map(fn ($summary) => $summary->monitor->id)->all())->toBe([$nearer->id, $near->id])
        ->and($overflows->monitors->first()->distanceMetres)->toBeLessThan(200)
        ->and($overflows->spillRecency)->toBe(SpillRecency::DischargingNow)
        ->and($overflows->dischargingNowCount)->toBe(1)
        ->and($overflows->spillsLast30DaysCount)->toBe(1);
});

it('never reads all offline monitors near a beach as no spills', function () {
    $beach = BathingWater::factory()->create(['latitude' => 54.28, 'longitude' => -0.40]);
    OverflowMonitor::factory()->create(['latitude' => 54.281, 'longitude' => -0.40, 'status' => OverflowStatus::Offline]);

    expect(app(WaterBodyConditionsService::class)->buildBeach($beach)->overflows->spillRecency)->toBe(SpillRecency::Offline);
});

it('shows a pollution risk forecast only in season and until it expires', function () {
    $this->travelTo(now()->setDate(2026, 7, 1)->setTime(12, 0));
    $inSeason = BathingWater::factory()->create(['risk_level' => PollutionRisk::Increased, 'risk_expires_at' => now()->addHours(6)]);
    $expired = BathingWater::factory()->create(['risk_level' => PollutionRisk::Increased, 'risk_expires_at' => now()->subHour()]);

    $service = app(WaterBodyConditionsService::class);

    expect($service->summarise($inSeason)->currentRisk())->toBe(PollutionRisk::Increased)
        ->and($service->summarise($expired)->currentRisk())->toBeNull();

    $this->travelTo(now()->setDate(2026, 11, 1));

    expect($service->summarise($inSeason->fresh())->currentRisk())->toBeNull();
});

it("gathers a lake's bathing sites and measures overflows from each of them", function () {
    $lake = Lake::factory()->create(['latitude' => 54.36, 'longitude' => -2.94]);
    $south = BathingWater::factory()->create(['lake_id' => $lake->id, 'region_id' => $lake->region_id, 'name' => 'Windermere, Fellfoot', 'latitude' => 54.27, 'longitude' => -2.95]);
    BathingWater::factory()->create(['lake_id' => $lake->id, 'region_id' => $lake->region_id, 'name' => 'Windermere, Rayrigg Meadow', 'latitude' => 54.38, 'longitude' => -2.92]);
    BathingWaterSample::factory()->create(['bathing_water_id' => $south->id, 'e_coli' => 54]);
    // Near the southern site only, 10 km from the lake's centre.
    OverflowMonitor::factory()->create(['latitude' => 54.272, 'longitude' => -2.95]);

    $conditions = app(WaterBodyConditionsService::class)->buildLake($lake);

    expect($conditions->bathingWaters->pluck('bathingWater.name')->all())->toBe(['Windermere, Fellfoot', 'Windermere, Rayrigg Meadow'])
        ->and($conditions->bathingWaters->first()->latestSample()->e_coli)->toBe(54)
        ->and($conditions->overflows->monitors)->toHaveCount(1)
        ->and($conditions->level)->toBeNull();
});

it('adds the bathing water record to a spot that is a designated bathing water', function () {
    $spot = Spot::factory()->create();
    BathingWater::factory()->create(['spot_id' => $spot->id, 'name' => 'Wharfe at Cromwheel, Ilkley']);

    expect(app(SpotConditionsService::class)->build($spot)->bathingWater->bathingWater->name)->toBe('Wharfe at Cromwheel, Ilkley')
        ->and(app(SpotConditionsService::class)->build(Spot::factory()->create())->bathingWater)->toBeNull();
});

it('starts and ends the bathing season on UK dates, not UTC', function () {
    $site = BathingWater::factory()->create(['season_starts_on' => '2026-05-15', 'season_ends_on' => '2026-09-30']);

    // 00:30 BST on 15 May is still 14 May in UTC.
    expect($site->isInSeason(\Illuminate\Support\Carbon::parse('2026-05-14 23:30', 'UTC')))->toBeTrue()
        ->and($site->isInSeason(\Illuminate\Support\Carbon::parse('2026-05-14 22:30', 'UTC')))->toBeFalse()
        // 00:30 BST on 1 October is still 30 September in UTC.
        ->and($site->isInSeason(\Illuminate\Support\Carbon::parse('2026-09-30 23:30', 'UTC')))->toBeFalse();
});
