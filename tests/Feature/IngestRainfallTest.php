<?php

use App\Actions\Ingest\SyncRainfallReadings;
use App\Actions\Ingest\SyncRainfallStations;
use App\Actions\Rivers\LinkNearestRainfallStations;
use App\Models\RainfallStation;
use App\Models\Spot;
use Illuminate\Support\Facades\Http;

function environmentAgencyRainfallItem(string $reference, float $lat, float $long, ?array $measures = null): array
{
    return [
        'stationReference' => $reference,
        'notation' => $reference,
        'label' => 'Rainfall station',
        'gridReference' => 'SE123456',
        'lat' => $lat,
        'long' => $long,
        'measures' => $measures ?? [[
            '@id' => "http://example/id/measures/{$reference}-rainfall-tipping_bucket_raingauge-t-15_min-mm",
            'parameter' => 'rainfall',
            'period' => 900,
            'unitName' => 'mm',
        ]],
    ];
}

it('stores rain gauges near a spot and skips the rest', function () {
    Spot::factory()->create(['latitude' => 53.90, 'longitude' => -1.80]);

    Http::fake([
        'environment.data.gov.uk/*' => Http::response(['items' => [
            environmentAgencyRainfallItem('1001', 53.92, -1.78),
            environmentAgencyRainfallItem('1002', 54.40, -0.60),
            environmentAgencyRainfallItem('1003', 53.91, -1.81, [['@id' => 'http://example/id/measures/1003-rainfall-t-1_h-mm', 'parameter' => 'rainfall', 'period' => 3600, 'unitName' => 'mm']]),
        ]]),
    ]);

    $result = app(SyncRainfallStations::class)->handle();

    expect($result)->toBe(['stations' => 1])
        ->and(RainfallStation::query()->pluck('external_id')->all())->toBe(['1001'])
        ->and(RainfallStation::query()->first()->measure_id)->toBe('1001-rainfall-tipping_bucket_raingauge-t-15_min-mm');

    Http::assertSent(fn ($request) => $request['parameter'] === 'rainfall' && $request['dist'] >= 15);
});

it('links each spot to the nearest rain gauge within range', function () {
    $near = RainfallStation::factory()->create(['latitude' => 53.91, 'longitude' => -1.80]);
    RainfallStation::factory()->create(['latitude' => 53.95, 'longitude' => -1.80]);
    RainfallStation::factory()->create(['latitude' => 53.90, 'longitude' => -1.80, 'is_active' => false]);

    $spot = Spot::factory()->create(['latitude' => 53.90, 'longitude' => -1.80]);
    $remote = Spot::factory()->create(['latitude' => 54.40, 'longitude' => -0.60]);

    expect(app(LinkNearestRainfallStations::class)->handle())->toBe(['spots' => 1])
        ->and($spot->fresh()->rainfallStation->is($near))->toBeTrue()
        ->and($remote->fresh()->rainfall_station_id)->toBeNull();
});

it('fetches readings only for rain gauges a spot uses', function () {
    $used = RainfallStation::factory()->create();
    RainfallStation::factory()->create();
    Spot::factory()->create(['rainfall_station_id' => $used->id]);

    Http::fake([
        'environment.data.gov.uk/*' => Http::response(['items' => [
            ['dateTime' => now()->subMinutes(30)->toIso8601String(), 'value' => 0.2],
            ['dateTime' => now()->subMinutes(15)->toIso8601String(), 'value' => 1.4],
        ]]),
    ]);

    expect(app(SyncRainfallReadings::class)->handle())->toBe(['stations' => 1, 'readings' => 2, 'retired' => 0])
        ->and($used->readings()->pluck('millimetres')->all())->toBe([1.4, 0.2]);

    Http::assertSentCount(1);
});

it('retires a rain gauge that never reports so the spot can be relinked', function () {
    $silent = RainfallStation::factory()->create(['latitude' => 53.91, 'longitude' => -1.80]);
    $live = RainfallStation::factory()->create(['latitude' => 53.93, 'longitude' => -1.80]);
    $spot = Spot::factory()->create(['rainfall_station_id' => $silent->id, 'latitude' => 53.90, 'longitude' => -1.80]);

    Http::fake(['environment.data.gov.uk/*' => Http::response(['items' => []])]);

    expect(app(SyncRainfallReadings::class)->handle())->toBe(['stations' => 1, 'readings' => 0, 'retired' => 1])
        ->and($silent->fresh()->is_active)->toBeFalse();

    app(LinkNearestRainfallStations::class)->handle();

    expect($spot->fresh()->rainfallStation->is($live))->toBeTrue();
});
