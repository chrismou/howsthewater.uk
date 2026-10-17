<?php

use App\Actions\Ingest\PruneNaturalResourcesWalesData;
use App\Actions\Ingest\RecordFloodWarningHistory;
use App\Actions\Ingest\SyncFloodAreas;
use App\Actions\Ingest\SyncFloodWarnings;
use App\Actions\Ingest\SyncGaugeReadings;
use App\Actions\Ingest\SyncGaugeStations;
use App\Actions\Ingest\SyncRainfallReadings;
use App\Actions\Ingest\SyncRainfallStations;
use App\Data\FloodWarningSummary;
use App\Enums\Agency;
use App\Enums\FloodSeverity;
use App\Models\FloodArea;
use App\Models\FloodWarning;
use App\Models\FloodWarningEvent;
use App\Models\GaugeStation;
use App\Models\RainfallStation;
use App\Models\River;
use App\Models\Spot;
use App\Services\NaturalResourcesWales\Api;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A station from NRW's station data, with one parameter and its latest reading.
 *
 * @return array<string, mixed>
 */
function nrwStation(int $location, string $title, float $latitude, float $longitude, int $parameter, string $kind = 'River Level', float $value = 0.5, ?string $time = null, string $units = 'm', string $status = 'Online'): array
{
    return [
        'location' => $location,
        'nameEN' => "Station {$location}",
        'statusEN' => $status,
        'titleEn' => $title,
        'ngr' => 'SO1802739260',
        'latitude' => $latitude,
        'longitude' => $longitude,
        'parameters' => [[
            'parameter' => $parameter,
            'paramNameEN' => $kind,
            'units' => $units,
            'latestValue' => $value,
            'latestTime' => $time ?? now()->subMinutes(5)->utc()->format('Y-m-d\TH:i:s\Z'),
        ]],
    ];
}

/**
 * Fakes NRW's API and DataMapWales. History returns $history($parameter), as [time, value] pairs.
 *
 * @param  array<int, array<string, mixed>>  $stations
 * @param  array<int, array<string, mixed>>  $warnings  Feature properties
 * @param  array<string, array<int, array<string, mixed>>>  $areasByLayer  Area properties keyed by layer name
 */
function fakeNrw(array $stations = [], ?Closure $history = null, array $warnings = [], array $areasByLayer = []): void
{
    Http::fake([
        'api.naturalresources.wales/rivers-and-seas/v1/api/StationData/historical*' => fn (Request $request) => Http::response([
            'parameter' => (int) $request['parameter'],
            'parameterReadings' => collect($history !== null ? $history($request['parameter']) : [])
                ->map(fn (array $pair) => ['time' => $pair[0], 'value' => $pair[1]])
                ->all(),
        ]),
        'api.naturalresources.wales/rivers-and-seas/v1/api/StationData' => Http::response($stations),
        'api.naturalresources.wales/floodwarnings/v3/all' => Http::response([
            'type' => 'FeatureCollection',
            'features' => collect($warnings)->map(fn (array $properties) => ['type' => 'Feature', 'properties' => $properties])->all(),
        ]),
        'datamap.gov.wales/*' => fn (Request $request) => Http::response([
            'type' => 'FeatureCollection',
            'features' => collect($areasByLayer[$request['typeNames']] ?? [])->map(fn (array $properties) => ['type' => 'Feature', 'properties' => $properties])->all(),
        ]),
        'environment.data.gov.uk/*' => Http::response(['items' => []]),
        'timeseries.sepa.org.uk/*' => Http::response([]),
    ]);
}

/**
 * Readings every 15 minutes from $from to now, as [time, value] pairs.
 *
 * @return array<int, array{0: string, 1: float}>
 */
function nrwSeries(Carbon $from, Closure $value): array
{
    $pairs = [];

    for ($time = $from->copy()->utc()->startOfMinute(); $time->lte(now()); $time->addMinutes(15)) {
        $pairs[] = [$time->format('Y-m-d\TH:i:s\Z'), $value($time)];
    }

    return $pairs;
}

beforeEach(function () {
    config([
        'services.nrw.levels_key' => 'test-levels-key',
        'services.nrw.flood_warnings_key' => 'test-warnings-key',
    ]);
});

it('discovers NRW gauges on a river by the river named in their title', function () {
    $river = River::factory()->withCentreline()->create(['name' => 'Afon Teifi', 'aliases' => ['Afon Teifi', 'River Teifi']]);

    fakeNrw([
        nrwStation(4014, 'Teifi at Glan Teifi', 53.90, -1.80, 197),
        nrwStation(4015, 'Teifi at Faraway', 52.10, -4.60, 198),
        nrwStation(4019, 'River Teifi at Llandysul', 53.95, -1.90, 202),
        nrwStation(4016, 'Cych at Abercych', 53.90, -1.80, 199),
        nrwStation(4017, 'Teifi at Cardigan', 53.85, -1.70, 200, 'Tide Level', units: 'mAOD'),
        nrwStation(4018, 'Teifi at Old Gauge', 53.85, -1.70, 201, time: now()->subMonths(3)->utc()->format('Y-m-d\TH:i:s\Z')),
    ]);

    expect(app(SyncGaugeStations::class)->handle($river))->toBe(['stations' => 3]);

    $gauge = GaugeStation::query()->where('external_id', 'NRW4014')->sole();

    expect($gauge->network)->toBe(Agency::NaturalResourcesWales)
        ->and($gauge->river_id)->toBe($river->id)
        ->and($gauge->river_name)->toBe('Teifi')
        ->and($gauge->measure_id)->toBe('197')
        ->and($gauge->unit)->toBe('m')
        ->and($gauge->is_active)->toBeTrue()
        ->and(GaugeStation::query()->where('external_id', 'NRW4018')->value('is_active'))->toBeFalse();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.naturalresources.wales') && $request->header('Ocp-Apim-Subscription-Key') === ['test-levels-key']);
});

it('reads every NRW gauge from one list, and fills new gauges from their history a few per run', function () {
    $current = GaugeStation::factory()->create(['external_id' => 'NRW1', 'network' => Agency::NaturalResourcesWales, 'measure_id' => '101']);
    $current->readings()->create(['measured_at' => now()->subMinutes(20), 'value' => 0.4]);
    $new = collect(range(2, 6))->map(fn (int $location) => GaugeStation::factory()->create([
        'external_id' => "NRW{$location}",
        'network' => Agency::NaturalResourcesWales,
        'measure_id' => (string) (100 + $location),
        'typical_range_low' => null,
        'typical_range_high' => null,
    ]));

    fakeNrw(
        collect(range(1, 6))->map(fn (int $location) => nrwStation($location, "Wye at Place {$location}", 52.0, -3.2, 100 + $location, value: 0.6))->all(),
        fn () => nrwSeries(now()->subDays(200), fn (Carbon $time) => $time->dayOfYear % 10 / 10),
    );

    $result = app(SyncGaugeReadings::class)->handle();

    expect($result['stations'])->toBe(6)
        ->and($current->readings()->count())->toBe(2)
        ->and($current->readings()->first()->value)->toBe(0.6);

    $filled = $new->filter(fn (GaugeStation $gauge) => $gauge->readings()->count() > 1);

    // A week of 15 minute readings, plus the latest from the list.
    expect($filled)->toHaveCount(3)
        ->and($filled->first()->readings()->count())->toBeGreaterThan(7 * 96 - 2)
        ->and($filled->first()->readings()->where('measured_at', '<', now()->subDays(7)->subMinute())->exists())->toBeFalse()
        ->and($filled->first()->fresh()->typical_range_low)->toBe(0.0)
        ->and($filled->first()->fresh()->typical_range_high)->toBe(0.9)
        ->and($new->diff($filled)->every(fn (GaugeStation $gauge) => $gauge->readings()->count() === 1))->toBeTrue();

    Http::assertSentCount(4);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'historical') && $request['location'] === '2' && $request['parameter'] === '102');
});

it('skips the history call for an NRW gauge with only a short gap', function () {
    $gauge = GaugeStation::factory()->create(['external_id' => 'NRW1', 'network' => Agency::NaturalResourcesWales, 'measure_id' => '101']);
    $gauge->readings()->create(['measured_at' => now()->subMinutes(35), 'value' => 0.4]);

    fakeNrw([nrwStation(1, 'Wye at Glasbury', 52.0, -3.2, 101)]);

    app(SyncGaugeReadings::class)->handle();

    expect($gauge->readings()->count())->toBe(2);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'historical'));
});

it('stops calling NRW once its call allowance is used up', function () {
    Log::spy();
    GaugeStation::factory()->create(['external_id' => 'NRW1', 'network' => Agency::NaturalResourcesWales, 'measure_id' => '101']);
    fakeNrw([nrwStation(1, 'Wye at Glasbury', 52.0, -3.2, 101)]);

    foreach (range(1, 9) as $call) {
        RateLimiter::hit('nrw-api:levels:minute', 60);
    }

    expect(app(SyncGaugeReadings::class)->handle()['readings'])->toBe(0);

    Http::assertNothingSent();
    Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message) => str_contains($message, 'allowance used up'));
});

it('stores NRW rain gauges near a spot and fills any missed 15 minute total', function () {
    $spot = Spot::factory()->create(['latitude' => 52.05, 'longitude' => -3.20]);

    fakeNrw(
        [
            nrwStation(1000, 'Glasbury raingauge', 52.06, -3.21, 10095, 'Rainfall', 0.4, units: 'mm'),
            nrwStation(1001, 'Far raingauge', 53.20, -4.10, 10096, 'Rainfall', units: 'mm'),
        ],
        fn () => nrwSeries(now()->subHour(), fn () => 0.2),
    );

    expect(app(SyncRainfallStations::class)->handle())->toBe(['stations' => 1]);

    $gauge = RainfallStation::query()->sole();
    $spot->update(['rainfall_station_id' => $gauge->id]);
    $gauge->readings()->create(['measured_at' => now()->subMinutes(50), 'millimetres' => 0.0]);

    expect($gauge->external_id)->toBe('NRW1000')
        ->and($gauge->network)->toBe(Agency::NaturalResourcesWales)
        ->and($gauge->measure_id)->toBe('10095')
        ->and(app(SyncRainfallReadings::class)->handle()['retired'])->toBe(0)
        ->and($gauge->readings()->count())->toBeGreaterThan(3);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'historical') && $request['parameter'] === '10095');
});

it('links Welsh spots to NRW flood areas that name their river', function () {
    $taff = River::factory()->create(['name' => 'River Taff', 'aliases' => ['River Taff']]);
    $rhondda = River::factory()->create(['name' => 'River Rhondda', 'aliases' => ['River Rhondda']]);
    $dee = River::factory()->create(['name' => 'River Dee', 'aliases' => ['River Dee']]);
    $pontypridd = Spot::factory()->create(['river_id' => $taff->id, 'latitude' => 51.6006, 'longitude' => -3.3420]);
    $trehafod = Spot::factory()->create(['river_id' => $rhondda->id, 'latitude' => 51.6100, 'longitude' => -3.3600]);
    $llangollen = Spot::factory()->create(['river_id' => $dee->id, 'latitude' => 52.9700, 'longitude' => -3.1700]);
    $english = Spot::factory()->create(['latitude' => 53.98, 'longitude' => -1.32]);

    fakeNrw(areasByLayer: [
        'inspire-nrw:NRW_FLOOD_WARNING' => [
            ['fwd_tacode' => '103FWF043', 'fwa_name' => 'River Taff at Pontypridd', 'descrip' => 'River Taff at Pontypridd', 'river_sea' => 'River Taff'],
            ['fwd_tacode' => '103FWF197167', 'fwa_name' => 'River Rhondda at Hopkinstown', 'descrip' => 'River Rhondda at Hopkinstown', 'river_sea' => 'Rhondda'],
        ],
        'inspire-nrw:NRW_FLOOD_WATCH_AREAS' => [
            ['fwd_tacode' => '101WAFLD10', 'fwa_name' => 'Lower Dee catchment', 'descrip' => 'Areas around the River Dee from Llangollen to Trevalyn Meadows', 'river_sea' => ' '],
        ],
    ]);

    app(SyncFloodAreas::class)->handle();

    expect($pontypridd->floodAreas()->pluck('code')->all())->toBe(['103FWF043'])
        ->and($trehafod->floodAreas()->pluck('code')->all())->toBe(['103FWF197167'])
        ->and($llangollen->floodAreas()->pluck('code')->all())->toBe(['101WAFLD10'])
        ->and(FloodArea::query()->where('code', '103FWF043')->value('agency'))->toBe(Agency::NaturalResourcesWales)
        ->and($english->floodAreas()->exists())->toBeFalse();

    // Two layers for each of the three Welsh spots; the English spot is not searched there.
    expect(Http::recorded(fn (Request $request) => str_contains($request->url(), 'datamap.gov.wales')))->toHaveCount(6);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'datamap.gov.wales') && str_contains((string) $request['cql_filter'], 'POINT(-3.342000 51.600600),1000,meters'));
});

it('keeps NRW and Environment Agency warnings and history apart', function () {
    $spot = Spot::factory()->create();
    $welsh = FloodArea::factory()->hasAttached($spot)->create(['code' => '103FWF043', 'agency' => Agency::NaturalResourcesWales]);
    $english = FloodArea::factory()->hasAttached($spot)->create(['code' => '122WAF933']);
    FloodWarning::factory()->create(['flood_area_id' => $english->id]);
    FloodWarningEvent::factory()->create(['flood_area_id' => $english->id, 'ended_at' => null]);

    fakeNrw(warnings: [[
        'FWACODE' => '103FWF043',
        'DESCRIPTION' => 'River Taff at Pontypridd',
        'SEVERITYVALUE' => 2,
        'TIMERAISED' => '2026-10-17T06:30:00',
        'SEVERITY_CHANGED' => '2026-10-17T06:30:00',
        'RIM_CHANGED' => '2026-10-17T07:00:00',
        'RIM_ENGLISH' => 'River levels are rising on the Taff.',
    ], [
        'FWACODE' => '102WAF999',
        'DESCRIPTION' => 'Somewhere else in Wales',
        'SEVERITYVALUE' => 3,
        'TIMERAISED' => '2026-10-17T05:00:00',
    ]]);

    $feed = app(\App\Services\NaturalResourcesWales\FloodWarningsClient::class)->floodWarnings();

    expect(app(SyncFloodWarnings::class)->handle($feed, Agency::NaturalResourcesWales))->toBe(['warnings' => 1])
        ->and(app(RecordFloodWarningHistory::class)->handle($feed, Agency::NaturalResourcesWales))->toBe(['opened' => 2, 'closed' => 0]);

    $warning = FloodWarning::query()->where('flood_area_id', $welsh->id)->with('floodArea')->sole();
    $summary = FloodWarningSummary::fromWarning($warning);

    expect($warning->severity_level)->toBe(FloodSeverity::FloodWarning)
        ->and($warning->message)->toBe('River levels are rising on the Taff.')
        ->and($warning->raised_at->toIso8601String())->toBe('2026-10-17T05:30:00+00:00')
        ->and($summary->agency)->toBe(Agency::NaturalResourcesWales)
        ->and($summary->url())->toBe('https://flood-warning.naturalresources.wales/')
        ->and(FloodArea::query()->where('code', '102WAF999')->value('agency'))->toBe(Agency::NaturalResourcesWales)
        ->and(FloodWarning::query()->where('flood_area_id', $english->id)->exists())->toBeTrue()
        ->and(FloodWarningEvent::query()->where('flood_area_id', $english->id)->whereNull('ended_at')->exists())->toBeTrue();

    // An empty Environment Agency feed lifts the English warning but leaves the Welsh ones open.
    app(SyncFloodWarnings::class)->handle(collect());
    app(RecordFloodWarningHistory::class)->handle(collect());

    expect(FloodWarning::query()->pluck('flood_area_id')->all())->toBe([$welsh->id])
        ->and(FloodWarningEvent::query()->whereNull('ended_at')->count())->toBe(2);
});

it('makes no NRW requests while its keys are blank', function () {
    config(['services.nrw.levels_key' => '', 'services.nrw.flood_warnings_key' => null]);
    $river = River::factory()->withCentreline()->create(['name' => 'River Wye', 'aliases' => ['River Wye']]);
    GaugeStation::factory()->create(['external_id' => 'NRW1', 'network' => Agency::NaturalResourcesWales, 'measure_id' => '101']);
    Spot::factory()->create(['river_id' => $river->id, 'latitude' => 52.05, 'longitude' => -3.20]);
    fakeNrw();

    app(SyncGaugeStations::class)->handle($river);
    app(SyncGaugeReadings::class)->handle();
    app(SyncRainfallStations::class)->handle();
    app(SyncFloodAreas::class)->handle();
    $this->artisan('ingest:floods --quick')->assertSuccessful();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'naturalresources.wales') || str_contains($request->url(), 'datamap.gov.wales'));
});

it('is switched off in tests unless a test sets a key', function () {
    config(['services.nrw.levels_key' => env('NRW_LEVELS_API_KEY'), 'services.nrw.flood_warnings_key' => env('NRW_FLOOD_WARNINGS_API_KEY')]);

    expect(app(Api::class)->isEnabled(Api::LEVELS))->toBeFalse()
        ->and(app(Api::class)->isEnabled(Api::FLOOD_WARNINGS))->toBeFalse();
});

it('deletes NRW readings and flood history over a year old, and nothing else', function () {
    $nrwGauge = GaugeStation::factory()->create(['network' => Agency::NaturalResourcesWales]);
    $eaGauge = GaugeStation::factory()->create();
    $nrwRain = RainfallStation::factory()->create(['network' => Agency::NaturalResourcesWales]);
    $welsh = FloodArea::factory()->create(['agency' => Agency::NaturalResourcesWales]);
    $english = FloodArea::factory()->create();

    foreach ([$nrwGauge, $eaGauge] as $gauge) {
        $gauge->readings()->create(['measured_at' => now()->subDays(400), 'value' => 0.3]);
        $gauge->readings()->create(['measured_at' => now()->subDays(10), 'value' => 0.3]);
    }

    $nrwRain->readings()->create(['measured_at' => now()->subDays(400), 'millimetres' => 1.0]);
    $nrwRain->readings()->create(['measured_at' => now()->subDays(10), 'millimetres' => 1.0]);

    foreach ([$welsh, $english] as $area) {
        FloodWarningEvent::factory()->create(['flood_area_id' => $area->id, 'started_at' => now()->subDays(402), 'ended_at' => now()->subDays(400)]);
    }

    expect(app(PruneNaturalResourcesWalesData::class)->handle())->toBe(['levels' => 1, 'rainfall' => 1, 'flood_warnings' => 1])
        ->and($nrwGauge->readings()->count())->toBe(1)
        ->and($eaGauge->readings()->count())->toBe(2)
        ->and($nrwRain->readings()->count())->toBe(1)
        ->and(FloodWarningEvent::query()->where('flood_area_id', $english->id)->exists())->toBeTrue();
});
