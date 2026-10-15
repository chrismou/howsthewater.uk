<?php

use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use App\Actions\Ingest\RefreshRegions;
use Illuminate\Console\Scheduling\Schedule;
use App\Services\Conditions\ChangedRegions;
use App\Services\Conditions\RegionSummaries;
use App\Services\Conditions\SpotConditionsService;
use App\Services\Conditions\WaterBodyConditionsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

it('tracks the regions marked changed until a refresh takes them', function () {
    [$first, $second] = Region::factory()->count(2)->create();
    $changed = app(ChangedRegions::class);

    $changed->mark([$first->id]);
    $changed->mark([$first->id]);

    expect($changed->take())->toBe([$first->id])
        ->and($changed->take())->toBe([]);

    $changed->markAll();

    expect($changed->take())->toEqualCanonicalizing([$first->id, $second->id]);
});

it('has ingests mark every region changed instead of rebuilding them', function (string $command) {
    $region = Region::factory()->create();
    Http::fake(['*' => Http::response(['items' => [], 'features' => []])]);

    $this->artisan($command)->run();

    expect(app(ChangedRegions::class)->take())->toBe([$region->id]);
})->with(['ingest:overflows', 'ingest:gauges', 'ingest:rainfall', 'ingest:floods', 'rivers:positions']);

it('marks only regions with beaches or lakes after the bathing water ingest', function () {
    $coastal = Region::factory()->create();
    Region::factory()->create();
    Lake::factory()->create(['region_id' => $coastal->id]);
    Http::fake(['environment.data.gov.uk/*' => Http::response(['result' => ['items' => []]])]);

    $this->artisan('ingest:bathing-waters')->assertSuccessful();

    expect(app(ChangedRegions::class)->take())->toBe([$coastal->id]);
});

it('rewrites changed regions\' cached conditions in place, so visitors never wait on a rebuild', function () {
    $region = Region::factory()->create();
    $river = River::factory()->create(['region_id' => $region->id]);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'river_metres' => 50000]);
    $beach = BathingWater::factory()->create(['region_id' => $region->id]);
    $service = app(SpotConditionsService::class);

    // Cached before an overflow upstream starts discharging.
    expect($service->for($spot)->dischargingNowCount)->toBe(0);

    $monitor = OverflowMonitor::factory()->discharging()->create(['river_id' => $river->id, 'river_metres' => 45000]);
    SpillEvent::factory()->ongoing()->create(['overflow_monitor_id' => $monitor->id]);
    app(ChangedRegions::class)->mark([$region->id]);

    $this->artisan('regions:refresh')->expectsOutputToContain('Refreshed 1 region: 1 spots, 1 beaches and 0 lakes.')->assertSuccessful();

    DB::enableQueryLog();

    expect($service->for($spot)->dischargingNowCount)->toBe(1)
        ->and(app(WaterBodyConditionsService::class)->forBeach($beach)->bathingWater->bathingWater->id)->toBe($beach->id)
        ->and(app(RegionSummaries::class)->for($region)->dischargingNowCount())->toBe(1)
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('does nothing when no region changed, and refreshes every region with --all', function () {
    Region::factory()->count(2)->create();

    $this->artisan('regions:refresh')->expectsOutputToContain('No regions changed since the last refresh.')->assertSuccessful();
    $this->artisan('regions:refresh --all')->expectsOutputToContain('Refreshed 2 regions')->assertSuccessful();
});

it('keeps the marks when a refresh fails, so the next run retries those regions', function () {
    $region = Region::factory()->create();
    app(ChangedRegions::class)->mark([$region->id]);
    $this->mock(RefreshRegions::class)->shouldReceive('handle')->andThrow(new RuntimeException('Database went away'));

    expect(fn () => $this->artisan('regions:refresh')->run())->toThrow(RuntimeException::class);
    expect(app(ChangedRegions::class)->take())->toBe([$region->id]);
});

it('clears the marks on a full refresh, so the next run does not repeat it', function () {
    $region = Region::factory()->create();
    app(ChangedRegions::class)->mark([$region->id]);

    $this->artisan('regions:refresh --all')->assertSuccessful();

    expect(app(ChangedRegions::class)->take())->toBe([]);
});

it('refreshes only marked regions\' published main river spots and their lakes', function () {
    [$marked, $unmarked] = Region::factory()->count(2)->create();
    $river = River::factory()->create(['region_id' => $marked->id]);
    Spot::factory()->create(['river_id' => $river->id]);
    Spot::factory()->unpublished()->create(['river_id' => $river->id]);
    Spot::factory()->create(['river_id' => River::factory()->create(['region_id' => $marked->id, 'parent_river_id' => $river->id])->id]);
    Spot::factory()->create(['river_id' => River::factory()->create(['region_id' => $unmarked->id])->id]);
    Lake::factory()->create(['region_id' => $marked->id]);
    Lake::factory()->create(['region_id' => $unmarked->id]);
    app(ChangedRegions::class)->mark([$marked->id]);

    $this->artisan('regions:refresh')->expectsOutputToContain('Refreshed 1 region: 1 spots, 0 beaches and 1 lakes.')->assertSuccessful();
});

it('runs the refresh after every ingest, with overlap locks that cannot outlive a killed run for long', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events->last()->command)->toContain('regions:refresh')
        ->and($events->filter->withoutOverlapping)->each(fn ($event) => $event->expiresAt->toBeLessThanOrEqual(60))
        ->and($events->filter->withoutOverlapping->count())->toBe(6);
});
