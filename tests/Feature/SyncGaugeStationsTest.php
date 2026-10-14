<?php

use App\Actions\Ingest\SyncGaugeStations;
use App\Models\GaugeStation;
use App\Models\River;
use Illuminate\Support\Facades\Http;

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
