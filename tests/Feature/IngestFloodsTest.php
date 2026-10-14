<?php

use App\Actions\Ingest\SyncFloodAreas;
use App\Actions\Ingest\SyncFloodWarnings;
use App\Enums\FloodSeverity;
use App\Models\FloodArea;
use App\Models\FloodWarning;
use App\Models\River;
use App\Models\Spot;
use App\Services\Conditions\SpotConditionsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function environmentAgencyFloodAreaItem(string $code, string $riverOrSea): array
{
    return [
        'fwdCode' => $code,
        'notation' => $code,
        'label' => "Area {$code}",
        'description' => "Description of {$code}",
        'riverOrSea' => $riverOrSea,
    ];
}

function environmentAgencyFloodItem(string $code, int $severityLevel, string $message = 'Levels are rising.'): array
{
    return [
        'floodAreaID' => $code,
        'floodArea' => ['notation' => $code],
        'severityLevel' => $severityLevel,
        'message' => $message,
        'timeRaised' => '2026-10-14T08:47:26',
        'timeMessageChanged' => '2026-10-14T09:15:00',
    ];
}

it('links spots to flood areas that name their river', function () {
    $river = River::factory()->create(['name' => 'River Nidd', 'aliases' => ['River Nidd']]);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'latitude' => 53.98, 'longitude' => -1.32]);

    Http::fake([
        'environment.data.gov.uk/*' => Http::response(['items' => [
            environmentAgencyFloodAreaItem('122WAF933', 'River Nidd'),
            environmentAgencyFloodAreaItem('122FWF008', 'River Ouse, River Nidd'),
            environmentAgencyFloodAreaItem('122WAF943', 'River Wharfe, Kel Beck'),
        ]]),
    ]);

    expect(app(SyncFloodAreas::class)->handle())->toBe(['spots' => 1, 'areas' => 2])
        ->and($spot->floodAreas()->orderBy('code')->pluck('code')->all())->toBe(['122FWF008', '122WAF933'])
        ->and($spot->fresh()->flood_areas_searched_at)->not->toBeNull()
        ->and(FloodArea::query()->where('code', '122WAF943')->exists())->toBeFalse();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'id/floodAreas') && (float) $request['dist'] === 1.0);
});

it('only searches spots never searched unless asked to refresh them all', function () {
    Spot::factory()->create(['flood_areas_searched_at' => now()->subDay()]);
    Spot::factory()->create();

    Http::fake(['environment.data.gov.uk/*' => Http::response(['items' => []])]);

    app(SyncFloodAreas::class)->handle(onlyUnsearched: true);
    Http::assertSentCount(1);

    app(SyncFloodAreas::class)->handle();
    Http::assertSentCount(3);
});

it('stores warnings in force for linked areas and removes lifted ones', function () {
    $spot = Spot::factory()->create();
    $alerted = FloodArea::factory()->hasAttached($spot)->create(['code' => '122WAF933']);
    $lifted = FloodArea::factory()->hasAttached($spot)->create(['code' => '122FWF008']);
    $missing = FloodArea::factory()->hasAttached($spot)->create(['code' => '122FWF012']);
    FloodWarning::factory()->create(['flood_area_id' => $lifted->id]);
    FloodWarning::factory()->create(['flood_area_id' => $missing->id]);

    Http::fake([
        'environment.data.gov.uk/*' => Http::response(['items' => [
            environmentAgencyFloodItem('122WAF933', 2, "Flooding is expected.\nAvoid the riverside."),
            environmentAgencyFloodItem('122FWF008', 4),
            environmentAgencyFloodItem('054WACDV4A', 3),
        ]]),
    ]);

    expect(app(SyncFloodWarnings::class)->handle())->toBe(['warnings' => 1]);

    $warning = FloodWarning::query()->sole();

    expect($warning->flood_area_id)->toBe($alerted->id)
        ->and($warning->severity_level)->toBe(FloodSeverity::FloodWarning)
        ->and($warning->message)->toBe("Flooding is expected.\nAvoid the riverside.")
        ->and($warning->raised_at->toIso8601String())->toBe('2026-10-14T07:47:26+00:00');
});

it('adds warnings to spot conditions, most severe first', function () {
    $spot = Spot::factory()->create();
    $alert = FloodArea::factory()->hasAttached($spot)->create(['label' => 'Lower River Wharfe']);
    $severe = FloodArea::factory()->hasAttached($spot)->create(['label' => 'River Wharfe at Otley']);
    FloodWarning::factory()->create(['flood_area_id' => $alert->id]);
    FloodWarning::factory()->severity(FloodSeverity::SevereFloodWarning)->create(['flood_area_id' => $severe->id]);
    FloodWarning::factory()->create();

    $conditions = app(SpotConditionsService::class)->build($spot);

    expect($conditions->floodWarnings->pluck('areaLabel')->all())->toBe(['River Wharfe at Otley', 'Lower River Wharfe'])
        ->and($conditions->floodWarnings->first()->url())->toBe('https://check-for-flooding.service.gov.uk/target-area/'.$severe->code)
        ->and($conditions->spot->relationLoaded('floodAreas'))->toBeFalse();
});

it('flags a warning no poll has confirmed for six hours', function () {
    $spot = Spot::factory()->create();
    $area = FloodArea::factory()->hasAttached($spot)->create();
    FloodWarning::factory()->create(['flood_area_id' => $area->id, 'checked_at' => now()->subHours(7)]);

    expect(app(SpotConditionsService::class)->build($spot)->floodWarnings->first()->isStale())->toBeTrue();
});

it('links new spots and syncs warnings from the scheduled command', function () {
    $river = River::factory()->create(['name' => 'River Nidd', 'aliases' => ['River Nidd']]);
    $spot = Spot::factory()->create(['river_id' => $river->id]);

    Http::fake([
        'environment.data.gov.uk/flood-monitoring/id/floodAreas*' => Http::response(['items' => [environmentAgencyFloodAreaItem('122WAF933', 'River Nidd')]]),
        'environment.data.gov.uk/flood-monitoring/id/floods*' => Http::response(['items' => [environmentAgencyFloodItem('122WAF933', 3)]]),
    ]);

    $this->artisan('ingest:floods')
        ->expectsOutputToContain('1 flood alerts and warnings in force')
        ->assertSuccessful();

    expect($spot->floodAreas()->sole()->warning->severity_level)->toBe(FloodSeverity::FloodAlert);
});

it('still syncs warnings when the flood area search fails', function () {
    Spot::factory()->create();

    Http::fake([
        'environment.data.gov.uk/flood-monitoring/id/floodAreas*' => Http::response('', 500),
        'environment.data.gov.uk/flood-monitoring/id/floods*' => Http::response(['items' => []]),
    ]);

    $this->artisan('ingest:floods')->assertFailed();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'id/floods'));
});
