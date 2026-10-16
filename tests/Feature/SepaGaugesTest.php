<?php

use App\Actions\Ingest\SyncGaugeReadings;
use App\Actions\Ingest\SyncGaugeStations;
use App\Actions\Ingest\SyncRainfallReadings;
use App\Actions\Ingest\SyncRainfallStations;
use App\Enums\GaugeNetwork;
use App\Models\GaugeStation;
use App\Models\RainfallStation;
use App\Models\River;
use App\Models\Spot;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Fakes SEPA's KiWIS API by request type. Lists are a header row followed by rows.
 *
 * @param  array<string, array<int, array<int, mixed>>|callable>  $responses
 */
function fakeSepa(array $responses): void
{
    Http::fake([
        'timeseries.sepa.org.uk/*' => function (Request $request) use ($responses) {
            $key = $request['request'].($request['parametertype_name'] ?? '' ? ':'.$request['parametertype_name'] : '');
            $response = $responses[$key] ?? $responses[$request['request']] ?? [];

            return Http::response(is_callable($response) ? $response($request) : $response);
        },
        'environment.data.gov.uk/*' => Http::response(['items' => []]),
    ]);
}

it('discovers SEPA level gauges on a river by its SEPA name, with SEPA normal range', function () {
    $river = River::factory()->withCentreline()->create(['name' => 'River Tweed', 'aliases' => ['River Tweed']]);
    $recent = now()->subHour()->toIso8601String();

    fakeSepa([
        'getStationList' => [['station_no', 'river_name'], ['100', 'Tweed'], ['200', 'Tweed'], ['300', 'Tweed'], ['400', 'Teviot']],
        'getTimeseriesList:S' => function (Request $request) use ($recent) {
            if ($request['ts_name'] === 'HydrologicalYear.M*') {
                return [['station_no', 'ts_id', 'ts_name'], ['100', '901', 'HydrologicalYear.Min'], ['100', '902', 'HydrologicalYear.Max'], ['100', '903', 'HydrologicalYear.Mean']];
            }

            return [
                ['station_name', 'station_no', 'station_latitude', 'station_longitude', 'ts_id', 'ts_unitsymbol', 'from', 'to'],
                ['Peebles', '100', '53.90', '-1.80', '55822010', 'm', '1948-08-09T22:45:00.000Z', $recent],
                ['Glenbreck', '200', '53.85', '-1.70', '59631010', 'm', '2008-10-10T00:00:00.000Z', '2024-06-20T12:45:00.000Z'],
                ['Far away', '300', '56.40', '-3.40', '57000010', 'm', '1990-01-01T00:00:00.000Z', $recent],
                ['Hawick', '400', '53.90', '-1.80', '56016010', 'm', '1986-01-01T09:00:00.000Z', $recent],
            ];
        },
        'getTimeseriesValues' => [
            ['ts_id' => '901', 'data' => [['2023-10-01T09:00:00.000Z', 0.2], ['2024-10-01T09:00:00.000Z', 0.3], ['2025-10-01T09:00:00.000Z', 0.25]]],
            ['ts_id' => '902', 'data' => [['2023-10-01T09:00:00.000Z', 2.0], ['2024-10-01T09:00:00.000Z', 3.1], ['2025-10-01T09:00:00.000Z', 2.4]]],
        ],
    ]);

    expect(app(SyncGaugeStations::class)->handle($river))->toBe(['stations' => 2]);

    $peebles = GaugeStation::query()->where('external_id', 'SEPA100')->firstOrFail();

    expect($peebles->network)->toBe(GaugeNetwork::Sepa)
        ->and($peebles->measure_id)->toBe('55822010')
        ->and($peebles->unit)->toBe('m')
        ->and($peebles->river_id)->toBe($river->id)
        ->and($peebles->typical_range_low)->toBe(0.25)
        ->and($peebles->typical_range_high)->toBe(2.4)
        ->and($peebles->is_active)->toBeTrue()
        ->and(GaugeStation::query()->where('external_id', 'SEPA200')->value('is_active'))->toBeFalse()
        ->and(GaugeStation::query()->whereIn('external_id', ['SEPA300', 'SEPA400'])->exists())->toBeFalse();
});

it('still discovers Environment Agency gauges when SEPA fails, and logs the failure once', function () {
    Sleep::fake();
    Log::spy();
    $first = River::factory()->create(['name' => 'River One', 'aliases' => ['River One']]);
    $second = River::factory()->create(['name' => 'River Two', 'aliases' => ['River Two']]);

    Http::fake([
        'timeseries.sepa.org.uk/*' => Http::response(['type' => 'error', 'code' => 'CreditsExhausted', 'message' => 'Daily credits used up']),
        'environment.data.gov.uk/*' => Http::response(['items' => [[
            'stationReference' => 'L0001',
            'label' => 'Testville',
            'lat' => 53.90,
            'long' => -1.80,
            'status' => 'http://environment.data.gov.uk/flood-monitoring/def/core/statusActive',
            'measures' => [['@id' => 'http://example/id/measures/L0001-level-stage-i-15_min-m', 'parameter' => 'level', 'qualifier' => 'Stage', 'unitName' => 'm']],
        ]]]),
    ]);

    $sync = app(SyncGaugeStations::class);

    expect($sync->handle($first))->toBe(['stations' => 1])
        ->and($sync->handle($second))->toBe(['stations' => 1]);

    Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message) => str_contains($message, 'Daily credits used up'));
});

it('reads up to date SEPA gauges in one request and long silent ones on their own', function () {
    $current = GaugeStation::factory()->sepa()->create(['measure_id' => '111']);
    $current->readings()->create(['measured_at' => now()->subMinutes(30), 'value' => 0.5]);
    $silent = GaugeStation::factory()->sepa()->create(['measure_id' => '222']);
    $silent->readings()->create(['measured_at' => now()->subDays(3), 'value' => 0.4]);

    fakeSepa([
        'getTimeseriesValues' => fn (Request $request) => collect(explode(',', $request['ts_id']))
            ->map(fn (string $id) => ['ts_id' => $id, 'data' => [[now()->subMinutes(15)->utc()->format('Y-m-d\TH:i:s.v\Z'), 0.6], [now()->subMinutes(5)->utc()->format('Y-m-d\TH:i:s.v\Z'), null]]])
            ->all(),
    ]);

    expect(app(SyncGaugeReadings::class)->handle())->toBe(['stations' => 2, 'readings' => 2])
        ->and($current->readings()->first()->value)->toBe(0.6)
        ->and($silent->readings()->count())->toBe(2);

    $sepaRequests = Http::recorded(fn (Request $request) => str_contains($request->url(), 'sepa'))->map(fn (array $pair) => $pair[0]['ts_id']);

    expect($sepaRequests->all())->toBe(['111', '222']);
});

it('stores SEPA rain gauges near a spot and reads them', function () {
    Spot::factory()->create(['latitude' => 55.61, 'longitude' => -2.40]);
    $recent = now()->subHour()->toIso8601String();

    fakeSepa([
        'getTimeseriesList:Precip' => [
            ['station_name', 'station_no', 'station_latitude', 'station_longitude', 'ts_id', 'from', 'to'],
            ['Ormiston Mill', '15007', '55.5449', '-2.4733', '56089010', '2004-07-15T14:15:00.000Z', $recent],
            ['Gogarbank', '15196', '55.9283', '-3.3427', '56594010', '1999-12-01T00:15:00.000Z', $recent],
        ],
        'getTimeseriesValues' => [['ts_id' => '56089010', 'data' => [[now()->subMinutes(15)->utc()->format('Y-m-d\TH:i:s.v\Z'), 1.2]]]],
    ]);

    expect(app(SyncRainfallStations::class)->handle())->toBe(['stations' => 1]);

    $gauge = RainfallStation::query()->sole();
    Spot::query()->update(['rainfall_station_id' => $gauge->id]);

    expect($gauge->external_id)->toBe('SEPA15007')
        ->and($gauge->network)->toBe(GaugeNetwork::Sepa)
        ->and(app(SyncRainfallReadings::class)->handle())->toBe(['stations' => 1, 'readings' => 1, 'retired' => 0])
        ->and($gauge->readings()->value('millimetres'))->toBe(1.2);
});

it('keeps SEPA rain gauges when SEPA cannot be read, and still reads Environment Agency ones', function () {
    Sleep::fake();
    Log::spy();
    $sepa = RainfallStation::factory()->sepa()->create();
    $environmentAgency = RainfallStation::factory()->create();
    Spot::factory()->create(['rainfall_station_id' => $sepa->id]);
    Spot::factory()->create(['rainfall_station_id' => $environmentAgency->id]);

    Http::fake([
        'timeseries.sepa.org.uk/*' => Http::response('Bad gateway', 502),
        'environment.data.gov.uk/*' => Http::response(['items' => [['dateTime' => now()->subMinutes(15)->toIso8601String(), 'value' => 0.4]]]),
    ]);

    expect(app(SyncRainfallReadings::class)->handle())->toBe(['stations' => 2, 'readings' => 1, 'retired' => 0])
        ->and($sepa->fresh()->is_active)->toBeTrue();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_starts_with($message, 'SEPA rainfall readings failed'));
});

it('skips a SEPA gauge that the Environment Agency also publishes', function () {
    $river = River::factory()->withCentreline()->create(['name' => 'River Tweed', 'aliases' => ['River Tweed']]);
    GaugeStation::factory()->create(['external_id' => '021009', 'river_id' => $river->id, 'latitude' => 53.9001, 'longitude' => -1.8001]);

    fakeSepa([
        'getStationList' => [['station_no', 'river_name'], ['9514', 'Tweed'], ['15012', 'Tweed']],
        'getTimeseriesList:S' => [
            ['station_name', 'station_no', 'station_latitude', 'station_longitude', 'ts_id', 'ts_unitsymbol', 'from', 'to'],
            ['Norham', '9514', '53.90', '-1.80', '61557010', 'm', '1959-06-17T09:00:00.000Z', now()->toIso8601String()],
            ['Sprouston', '15012', '53.85', '-1.70', '56114010', 'm', '1969-05-28T09:15:00.000Z', now()->toIso8601String()],
        ],
    ]);

    expect(app(SyncGaugeStations::class)->handle($river))->toBe(['stations' => 1])
        ->and(GaugeStation::query()->where('network', GaugeNetwork::Sepa)->pluck('external_id')->all())->toBe(['SEPA15012']);
});
