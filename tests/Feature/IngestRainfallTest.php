<?php

use App\Actions\Ingest\SyncRainfallReadings;
use App\Actions\Ingest\SyncRainfallStations;
use App\Actions\Rivers\LinkNearestRainfallStations;
use App\Models\RainfallStation;
use App\Models\Region;
use App\Models\River;
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

    expect(app(LinkNearestRainfallStations::class)->handle())->toBe(['spots' => 1, 'beaches' => 0, 'lakes' => 0])
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

it('searches for rain gauges once per region rather than one circle around everything', function () {
    $yorkshire = Region::factory()->create();
    $thames = Region::factory()->create();
    Spot::factory()->create(['river_id' => River::factory()->create(['region_id' => $yorkshire->id])->id, 'latitude' => 53.90, 'longitude' => -1.80]);
    Spot::factory()->create(['river_id' => River::factory()->create(['region_id' => $thames->id])->id, 'latitude' => 51.50, 'longitude' => -0.90]);

    Http::fake(['environment.data.gov.uk/*' => Http::response(['items' => [
        environmentAgencyRainfallItem('1001', 53.91, -1.80),
        environmentAgencyRainfallItem('2002', 51.51, -0.90),
    ]])]);

    expect(app(SyncRainfallStations::class)->handle())->toBe(['stations' => 2]);

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request['dist'] < 60);
});

it('searches for rain gauges during a normal ingest only while a spot has never been searched', function () {
    $spot = Spot::factory()->create(['latitude' => 53.90, 'longitude' => -1.80]);

    Http::fake([
        'environment.data.gov.uk/*/id/stations*' => Http::response(['items' => [environmentAgencyRainfallItem('1001', 53.91, -1.80)]]),
        'environment.data.gov.uk/*' => Http::response(['items' => [['dateTime' => now()->subMinutes(15)->toIso8601String(), 'value' => 0.4]]]),
    ]);

    $this->artisan('ingest:rainfall')->expectsOutputToContain('1 rain gauges near spots, beaches and lakes; linked 1 spots, 0 beaches and 0 lakes.')->assertSuccessful();

    expect($spot->fresh()->rain_gauges_searched_at)->not->toBeNull()
        ->and($spot->fresh()->rainfall_station_id)->not->toBeNull();

    $this->artisan('ingest:rainfall')->doesntExpectOutputToContain('rain gauges near spots')->assertSuccessful();
});

it('finds rain gauges near beaches and lakes and links each to the nearest', function () {
    $region = Region::factory()->create();
    $beach = \App\Models\BathingWater::factory()->create(['region_id' => $region->id, 'latitude' => 54.28, 'longitude' => -0.40]);
    $lake = \App\Models\Lake::factory()->create(['region_id' => $region->id, 'latitude' => 54.36, 'longitude' => -2.94]);

    Http::fake([
        'environment.data.gov.uk/flood-monitoring/id/measures/*' => Http::response(['items' => [['dateTime' => now()->subMinutes(15)->toIso8601String(), 'value' => 0.4]]]),
        'environment.data.gov.uk/*' => Http::response(['items' => [
            environmentAgencyRainfallItem('2001', 54.29, -0.41),
            environmentAgencyRainfallItem('2002', 54.37, -2.95),
        ]]),
    ]);

    $this->artisan('ingest:rainfall')->assertSuccessful();

    expect($beach->fresh()->rainfallStation->external_id)->toBe('2001')
        ->and($lake->fresh()->rainfallStation->external_id)->toBe('2002')
        ->and($beach->fresh()->rain_gauges_searched_at)->not->toBeNull()
        ->and(\App\Models\RainfallStation::query()->inUse()->count())->toBe(2);
});

it('shows rainfall on beach and lake pages and in the API', function () {
    $region = Region::factory()->create();
    $station = RainfallStation::factory()->create(['latitude' => 54.29, 'longitude' => -0.41]);
    \App\Models\RainfallReading::factory()->create(['rainfall_station_id' => $station->id, 'measured_at' => now()->subHour(), 'millimetres' => 4.2]);
    $beach = \App\Models\BathingWater::factory()->create(['region_id' => $region->id, 'latitude' => 54.28, 'longitude' => -0.40, 'rainfall_station_id' => $station->id]);
    $lake = \App\Models\Lake::factory()->create(['region_id' => $region->id, 'rainfall_station_id' => $station->id]);

    $this->get(route('beaches.show', [$region, $beach]))->assertOk()->assertSeeInOrder(['Rainfall', '4.2 mm', 'from this beach']);
    $this->get(route('lakes.show', [$region, $lake]))->assertOk()->assertSeeInOrder(['Rainfall', '4.2 mm', 'from this lake']);
    $this->getJson(route('api.v1.beaches.show', [$region, $beach]))->assertOk()->assertJsonPath('data.rainfall.last_24_hours_mm', 4.2);
});
