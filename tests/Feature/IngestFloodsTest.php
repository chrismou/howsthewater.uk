<?php

use App\Actions\Ingest\RecordFloodWarningHistory;
use App\Actions\Ingest\SyncFloodAreas;
use App\Actions\Ingest\SyncFloodWarnings;
use App\Data\FloodWarningData;
use App\Enums\FloodSeverity;
use App\Models\FloodArea;
use App\Models\FloodWarning;
use App\Models\FloodWarningEvent;
use App\Models\River;
use App\Models\Spot;
use App\Services\Conditions\SpotConditionsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

it('skips the flood area search with --quick but still syncs warnings', function () {
    $spot = Spot::factory()->create();
    Http::fake(['environment.data.gov.uk/*' => Http::response(['items' => []])]);

    $this->artisan('ingest:floods --quick')->expectsOutputToContain('Skipped the flood area search')->assertSuccessful();

    expect($spot->fresh()->flood_areas_searched_at)->toBeNull();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'floodAreas'));
});

it('still syncs warnings and logs an error when the flood area search fails', function () {
    Spot::factory()->create();
    Log::spy();

    Http::fake([
        'environment.data.gov.uk/flood-monitoring/id/floodAreas*' => Http::response('', 500),
        'environment.data.gov.uk/flood-monitoring/id/floods*' => Http::response(['items' => []]),
    ]);

    $this->artisan('ingest:floods')->assertFailed();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'id/floods'));
    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_starts_with($message, 'Flood area search failed'));
});

/**
 * Records the history from one poll of the national flood feed holding these items.
 *
 * @param  array<int, array<string, mixed>>  $items
 * @return array{opened: int, closed: int}
 */
function recordFloodHistory(array $items): array
{
    return app(RecordFloodWarningHistory::class)->handle(collect($items)->map(fn (array $item) => FloodWarningData::fromEnvironmentAgency($item)));
}

it('records a history of flood warnings across England, not just our spots\' areas', function () {
    $counts = recordFloodHistory([
        [...environmentAgencyFloodItem('054WATBT1', 3), 'description' => 'The tidal Rivers Bure, Ant and Thurne', 'floodArea' => ['notation' => '054WATBT1', 'riverOrSea' => 'Bure, Ant, Thurne', 'county' => 'Norfolk'], 'timeSeverityChanged' => '2026-10-14T16:04:00'],
        environmentAgencyFloodItem('122FWF008', 4),
    ]);

    $event = FloodWarningEvent::query()->with('floodArea')->sole();

    expect($counts)->toBe(['opened' => 1, 'closed' => 0])
        ->and($event->floodArea->only(['code', 'label', 'river_or_sea', 'county']))->toBe(['code' => '054WATBT1', 'label' => 'The tidal Rivers Bure, Ant and Thurne', 'river_or_sea' => 'Bure, Ant, Thurne', 'county' => 'Norfolk'])
        ->and($event->severity_level)->toBe(FloodSeverity::FloodAlert)
        ->and($event->started_at->toIso8601String())->toBe('2026-10-14T15:04:00+00:00')
        ->and($event->isOngoing())->toBeTrue();
});

it('keeps one event while a warning stays in force, updating its message', function () {
    recordFloodHistory([environmentAgencyFloodItem('122WAF933', 3, 'Levels are rising.')]);
    $this->travel(15)->minutes();
    $counts = recordFloodHistory([environmentAgencyFloodItem('122WAF933', 3, 'Levels have peaked.')]);

    $event = FloodWarningEvent::query()->sole();

    expect($counts)->toBe(['opened' => 0, 'closed' => 0])
        ->and($event->message)->toBe('Levels have peaked.')
        ->and($event->last_seen_at->equalTo(now()->startOfSecond()))->toBeTrue();
});

it('closes an event and opens another when the severity changes', function () {
    recordFloodHistory([environmentAgencyFloodItem('122WAF933', 3)]);
    $counts = recordFloodHistory([[...environmentAgencyFloodItem('122WAF933', 2), 'timeSeverityChanged' => '2026-10-15T07:30:00']]);

    [$alert, $warning] = FloodWarningEvent::query()->orderBy('id')->get();

    expect($counts)->toBe(['opened' => 1, 'closed' => 1])
        ->and($alert->ended_at->toIso8601String())->toBe('2026-10-15T06:30:00+00:00')
        ->and($warning->severity_level)->toBe(FloodSeverity::FloodWarning)
        ->and($warning->started_at->toIso8601String())->toBe('2026-10-15T06:30:00+00:00')
        ->and($warning->isOngoing())->toBeTrue();
});

it('closes events the EA lifts, or that drop out of the feed', function () {
    recordFloodHistory([environmentAgencyFloodItem('122WAF933', 3), environmentAgencyFloodItem('122FWF008', 2)]);
    $this->travel(15)->minutes();
    $counts = recordFloodHistory([[...environmentAgencyFloodItem('122WAF933', 4), 'timeSeverityChanged' => now()->subMinutes(5)->timezone('Europe/London')->format('Y-m-d\TH:i:s')]]);

    $lifted = FloodWarningEvent::query()->whereRelation('floodArea', 'code', '122WAF933')->sole();
    $dropped = FloodWarningEvent::query()->whereRelation('floodArea', 'code', '122FWF008')->sole();

    expect($counts)->toBe(['opened' => 0, 'closed' => 2])
        ->and($lifted->ended_at->equalTo(now()->subMinutes(5)->startOfSecond()))->toBeTrue()
        ->and($dropped->ended_at->equalTo(now()->startOfSecond()))->toBeTrue()
        ->and(FloodWarningEvent::query()->whereNull('ended_at')->exists())->toBeFalse();
});

it('reads the national feed once per run for both current warnings and history', function () {
    $spot = Spot::factory()->create(['flood_areas_searched_at' => now()]);
    FloodArea::factory()->hasAttached($spot)->create(['code' => '122WAF933']);
    Http::fake(['environment.data.gov.uk/*' => Http::response(['items' => [environmentAgencyFloodItem('122WAF933', 2), environmentAgencyFloodItem('054WATBT1', 3)]])]);

    $this->artisan('ingest:floods')
        ->expectsOutputToContain('1 flood alerts and warnings in force for our spots; history: 2 opened, 0 closed.')
        ->assertSuccessful();

    Http::assertSentCount(1);
    expect(FloodWarning::query()->count())->toBe(1);
});
