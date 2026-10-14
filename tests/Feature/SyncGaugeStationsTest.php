<?php

use App\Actions\Ingest\SyncGaugeStations;
use App\Models\GaugeStation;
use App\Models\River;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

function environmentAgencyStationItem(string $reference, float $lat, float $long): array
{
    return [
        'stationReference' => $reference,
        'label' => $reference,
        'riverName' => 'River Test',
        'town' => 'Testville',
        'lat' => $lat,
        'long' => $long,
        'status' => 'http://environment.data.gov.uk/flood-monitoring/def/core/statusActive',
        'stageScale' => ['typicalRangeLow' => 0.2, 'typicalRangeHigh' => 1.1],
        'measures' => [['@id' => "http://example/id/measures/{$reference}-level-stage-i-15_min-m", 'parameter' => 'level', 'qualifier' => 'Stage', 'unitName' => 'm']],
    ];
}

it('skips same-named stations that sit nowhere near the river centreline', function () {
    $river = River::factory()->withCentreline()->create(['name' => 'River Test', 'aliases' => ['River Test']]);

    Http::fake([
        'environment.data.gov.uk/*' => Http::response(['items' => [
            environmentAgencyStationItem('L0001', 53.90, -1.80),
            environmentAgencyStationItem('L0002', 54.40, -3.40),
        ]]),
    ]);

    $result = app(SyncGaugeStations::class)->handle($river);

    expect($result)->toBe(['stations' => 1])
        ->and(GaugeStation::query()->pluck('external_id')->all())->toBe(['L0001']);
});

it('keeps every station when the river has no centreline yet', function () {
    $river = River::factory()->create(['name' => 'River Test', 'aliases' => ['River Test']]);

    Http::fake([
        'environment.data.gov.uk/*' => Http::response(['items' => [
            environmentAgencyStationItem('L0001', 53.90, -1.80),
            environmentAgencyStationItem('L0002', 54.40, -3.40),
        ]]),
    ]);

    expect(app(SyncGaugeStations::class)->handle($river))->toBe(['stations' => 2]);
});

it('keeps discovering gauges for other rivers when one request fails', function () {
    Sleep::fake();
    River::factory()->create(['name' => 'River Bad', 'aliases' => ['River Bad']]);
    River::factory()->create(['name' => 'River Good', 'aliases' => ['River Good']]);

    Http::fake([
        'environment.data.gov.uk/*riverName=River%20Bad*' => Http::response('Gateway timeout', 504),
        'environment.data.gov.uk/*riverName=River%20Good*' => Http::response(['items' => [environmentAgencyStationItem('L0009', 53.90, -1.80)]]),
        'environment.data.gov.uk/*' => Http::response(['items' => []]),
    ]);

    $this->artisan('ingest:gauges --stations')
        ->expectsOutputToContain('River Bad: gauge discovery failed')
        ->expectsOutputToContain('River Good: 1 gauge stations.')
        ->assertFailed();

    expect(GaugeStation::query()->pluck('external_id')->all())->toBe(['L0009']);
});

it('discovers gauges on rivers never searched during a normal ingest, and only those', function () {
    $new = River::factory()->withCentreline()->create(['name' => 'River New', 'aliases' => ['River New']]);
    $known = River::factory()->withCentreline()->create(['name' => 'River Known', 'aliases' => ['River Known'], 'gauges_discovered_at' => now()->subDay()]);
    River::factory()->create(['name' => 'River Unmapped', 'aliases' => ['River Unmapped']]);

    Http::fake([
        'environment.data.gov.uk/*/id/stations*' => Http::response(['items' => [environmentAgencyStationItem('L0010', 53.90, -1.80)]]),
        'environment.data.gov.uk/*' => Http::response(['items' => []]),
    ]);

    $this->artisan('ingest:gauges')->expectsOutputToContain('River New: 1 gauge stations.')->assertSuccessful();

    expect($new->fresh()->gauges_discovered_at)->not->toBeNull()
        ->and(GaugeStation::query()->first()->river_id)->toBe($new->id)
        ->and(GaugeStation::query()->first()->river_metres)->not->toBeNull();

    Http::assertNotSent(fn ($request) => str_contains(urldecode($request->url()), 'River Known') || str_contains(urldecode($request->url()), 'River Unmapped'));

    $this->artisan('ingest:gauges')->doesntExpectOutputToContain('gauge stations.')->assertSuccessful();
});
