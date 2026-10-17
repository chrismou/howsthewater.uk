<?php

use App\Actions\Ingest\SyncOverflowMonitors;
use App\Enums\OverflowStatus;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Region::factory()->create(['overflow_feeds' => ['yorkshire_water']]);
    pollOnly('yorkshire_water');
});

/**
 * Every configured feed is polled, so each test keeps only the feeds it fakes.
 */
function pollOnly(string ...$keys): void
{
    static $configured;
    $configured ??= config('services.overflow_feeds');

    config(['services.overflow_feeds' => Arr::only($configured, $keys)]);
}

function arcGisFeature(array $overrides = []): array
{
    return ['attributes' => array_merge([
        'Id' => 'YWS00001',
        'Company' => 'Yorkshire Water',
        'Status' => 0,
        'StatusStart' => 1760000000000,
        'LatestEventStart' => 1759990000000,
        'LatestEventEnd' => 1760000000000,
        'Latitude' => 53.9,
        'Longitude' => -1.7,
        'ReceivingWaterCourse' => 'River Wharfe',
        'LastUpdated' => 1760001000000,
    ], $overrides)];
}

function fakeStreamFeed(array $features): void
{
    Http::fake([
        'services-eu1.arcgis.com/*' => Http::response(['features' => $features, 'exceededTransferLimit' => false]),
    ]);
}

it('creates monitors and records their latest spill event', function () {
    fakeStreamFeed([arcGisFeature()]);

    $result = app(SyncOverflowMonitors::class)->handle();

    expect($result)->toBe(['monitors' => 1, 'monitors_added' => 1, 'events_opened' => 1, 'events_closed' => 0, 'feeds' => 1, 'failed' => []]);

    $monitor = OverflowMonitor::query()->where('external_id', 'YWS00001')->firstOrFail();

    expect($monitor->status)->toBe(OverflowStatus::NotDischarging)
        ->and($monitor->receiving_watercourse)->toBe('River Wharfe')
        ->and($monitor->spillEvents)->toHaveCount(1)
        ->and($monitor->spillEvents->first()->ended_at)->not->toBeNull();
});

it('treats #N/A watercourse names as unknown', function () {
    fakeStreamFeed([arcGisFeature(['ReceivingWaterCourse' => '#N/A'])]);

    app(SyncOverflowMonitors::class)->handle();

    expect(OverflowMonitor::query()->firstOrFail()->receiving_watercourse)->toBeNull();
});

it('opens an event while discharging and closes it once the feed reports an end', function () {
    Http::fake([
        'services-eu1.arcgis.com/*' => Http::sequence()
            ->push(['features' => [arcGisFeature([
                'Status' => 1,
                'StatusStart' => 1760100000000,
                'LatestEventStart' => 1760100000000,
                'LatestEventEnd' => 1760000000000,
            ])]])
            ->push(['features' => [arcGisFeature([
                'Status' => 0,
                'StatusStart' => 1760103600000,
                'LatestEventStart' => 1760100000000,
                'LatestEventEnd' => 1760103600000,
            ])]]),
    ]);

    app(SyncOverflowMonitors::class)->handle();

    $event = SpillEvent::query()->firstOrFail();

    expect($event->ended_at)->toBeNull()
        ->and(OverflowMonitor::query()->firstOrFail()->isDischarging())->toBeTrue();

    $result = app(SyncOverflowMonitors::class)->handle();

    expect($result['events_closed'])->toBe(1)
        ->and(SpillEvent::query()->count())->toBe(1)
        ->and($event->fresh()->ended_at->timestamp)->toBe(1760103600);
});

it('does not duplicate events on repeated syncs', function () {
    fakeStreamFeed([arcGisFeature()]);

    app(SyncOverflowMonitors::class)->handle();
    $result = app(SyncOverflowMonitors::class)->handle();

    expect($result['events_opened'])->toBe(0)
        ->and(SpillEvent::query()->count())->toBe(1);
});

it('follows pagination when the feed exceeds the transfer limit', function () {
    Http::fake([
        'services-eu1.arcgis.com/*' => Http::sequence()
            ->push(['features' => [arcGisFeature(['Id' => 'YWS00001'])], 'exceededTransferLimit' => true])
            ->push(['features' => [arcGisFeature(['Id' => 'YWS00002'])], 'exceededTransferLimit' => false]),
    ]);

    $result = app(SyncOverflowMonitors::class)->handle();

    expect($result['monitors'])->toBe(2)
        ->and(OverflowMonitor::query()->count())->toBe(2);
});

it('closes an event when the monitor stops discharging without the feed giving an end time', function () {
    Http::fake([
        'services-eu1.arcgis.com/*' => Http::sequence()
            ->push(['features' => [arcGisFeature(['Status' => 1, 'StatusStart' => 1760100000000, 'LatestEventStart' => 1760100000000, 'LatestEventEnd' => null])]])
            ->push(['features' => [arcGisFeature(['Status' => 0, 'StatusStart' => 1760107200000, 'LatestEventStart' => 1760100000000, 'LatestEventEnd' => null])]]),
    ]);

    app(SyncOverflowMonitors::class)->handle();
    $result = app(SyncOverflowMonitors::class)->handle();

    $event = SpillEvent::query()->firstOrFail();

    expect($result['events_closed'])->toBe(1)
        ->and($event->ended_at->timestamp)->toBe(1760107200)
        ->and(OverflowMonitor::query()->firstOrFail()->openSpillEvents)->toBeEmpty();
});

it('closes an older open event when a new one starts while still discharging', function () {
    Http::fake([
        'services-eu1.arcgis.com/*' => Http::sequence()
            ->push(['features' => [arcGisFeature(['Status' => 1, 'StatusStart' => 1760100000000, 'LatestEventStart' => 1760100000000, 'LatestEventEnd' => null])]])
            ->push(['features' => [arcGisFeature(['Status' => 1, 'StatusStart' => 1760110000000, 'LatestEventStart' => 1760110000000, 'LatestEventEnd' => null])]]),
    ]);

    app(SyncOverflowMonitors::class)->handle();
    $result = app(SyncOverflowMonitors::class)->handle();

    $events = SpillEvent::query()->orderBy('started_at')->get();

    expect($result)->toMatchArray(['events_opened' => 1, 'events_closed' => 1])
        ->and($events)->toHaveCount(2)
        ->and($events[0]->ended_at->timestamp)->toBe(1760110000)
        ->and($events[1]->ended_at)->toBeNull();
});

it('polls every configured feed, even one no region names yet, and keeps going when one fails', function () {
    Sleep::fake();
    config(['services.overflow_feeds' => [
        'yorkshire_water' => ['name' => 'Yorkshire Water', 'driver' => 'stream', 'url' => 'https://yorkshire.test/FeatureServer/0'],
        'southern_water' => ['name' => 'Southern Water', 'driver' => 'stream', 'url' => 'https://southern.test/FeatureServer/0'],
        'united_utilities' => ['name' => 'United Utilities', 'driver' => 'stream', 'url' => 'https://united.test/FeatureServer/0'],
    ]]);

    Http::fake([
        'yorkshire.test/*' => Http::response(['features' => [arcGisFeature(['Id' => 'YWS00001'])]]),
        'southern.test/*' => Http::response(['features' => [arcGisFeature(['Id' => 'SWS00001', 'Company' => 'Southern Water'])]]),
        'united.test/*' => Http::response('Service unavailable', 503),
    ]);

    $result = app(SyncOverflowMonitors::class)->handle();

    expect($result['feeds'])->toBe(2)
        ->and($result['failed'])->toBe(['United Utilities'])
        ->and(OverflowMonitor::query()->orderBy('external_id')->pluck('external_id')->all())->toBe(['SWS00001', 'YWS00001']);
});

it('treats a feed that only edits changed monitors as current when read, until the whole feed goes quiet', function () {
    $this->freezeTime();
    Region::factory()->create(['overflow_feeds' => ['northumbrian_water']]);
    pollOnly('yorkshire_water', 'northumbrian_water');
    $quiet = now()->subWeeks(3)->startOfSecond()->getTimestampMs();
    $lastEdited = now()->subHour()->getTimestampMs();
    Http::fake([
        'services-eu1.arcgis.com/1WqkK5cDKUbF0CkH/*' => Http::response(['features' => [arcGisFeature(['LastUpdated' => $quiet])]]),
        'services-eu1.arcgis.com/MSNNjkZ51iVh8yBj/*' => function () use ($quiet, &$lastEdited) {
            return Http::response(['features' => [
                arcGisFeature(['Id' => 'NWL00001', 'LastUpdated' => $quiet]),
                arcGisFeature(['Id' => 'NWL00002', 'LastUpdated' => $lastEdited]),
            ]]);
        },
    ]);
    $updatedAt = fn (string $id) => OverflowMonitor::query()->where('external_id', $id)->value('source_updated_at');

    app(SyncOverflowMonitors::class)->handle();

    // Yorkshire Water refreshes LastUpdated on every publish, so its own time stands.
    expect($updatedAt('NWL00001')->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($updatedAt('YWS00001')->getTimestampMs())->toBe($quiet);

    $lastEdited = now()->subHours(SyncOverflowMonitors::QUIET_FEED_HOURS + 1)->getTimestampMs();
    app(SyncOverflowMonitors::class)->handle();

    expect($updatedAt('NWL00001')->getTimestampMs())->toBe($quiet);
});

it('fails the ingest command and logs an error when a feed fails', function () {
    Sleep::fake();
    Log::spy();
    Http::fake(['*' => Http::response('Service unavailable', 503)]);

    $this->artisan('ingest:overflows')
        ->expectsOutputToContain('Yorkshire Water feed failed')
        ->assertFailed();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_starts_with($message, 'Storm overflow feed yorkshire_water failed'));
});

it('positions monitors the first time it sees them', function () {
    $river = River::factory()->withCentreline()->create(['name' => 'River Wharfe', 'aliases' => ['River Wharfe']]);
    fakeStreamFeed([arcGisFeature(['Latitude' => 53.90, 'Longitude' => -1.80])]);

    $this->artisan('ingest:overflows')->expectsOutputToContain('1 new monitors positioned.')->assertSuccessful();

    expect(OverflowMonitor::query()->first()->river_id)->toBe($river->id);

    $this->artisan('ingest:overflows')->doesntExpectOutputToContain('new monitors positioned')->assertSuccessful();
});

function scottishWaterRecord(array $overrides = []): array
{
    return array_merge([
        'ASSET_ID' => 'CSO000007',
        'OVERFLOW_STATUS_DESCRIPTION' => 'NO - No Overflows',
        'OVERFLOW_START_DATETIME' => '2026-10-15T09:20:00.000Z',
        'OVERFLOW_END_DATETIME' => '2026-10-15T09:30:00.000Z',
        'RECEIVING_WATER' => 'River Tweed',
        'DISCHARGE_OVERFLOW_LOCATION_LATITUDE' => '55.6',
        'DISCHARGE_OVERFLOW_LOCATION_LONGITUDE' => '-2.4',
        'LAST_TRANSMITTED_DATETIME' => '2026-10-15T18:10:00.000Z',
    ], $overrides);
}

it('reads Scottish Water\'s own API into the same monitors and spill events', function () {
    Region::factory()->create(['overflow_feeds' => ['scottish_water']]);
    pollOnly('yorkshire_water', 'scottish_water');
    Http::fake([
        'services-eu1.arcgis.com/*' => Http::response(['features' => []]),
        'api.scottishwater.co.uk/*' => Http::response(['last_updated' => 1792098080801, 'results' => [
            scottishWaterRecord(),
            scottishWaterRecord(['ASSET_ID' => 'CSO000008', 'OVERFLOW_STATUS_DESCRIPTION' => 'OF - Overflowing', 'OVERFLOW_START_DATETIME' => '2026-10-14T23:00:00.000Z', 'OVERFLOW_END_DATETIME' => '']),
            scottishWaterRecord(['ASSET_ID' => 'CSO000009', 'OVERFLOW_STATUS_DESCRIPTION' => 'RO - Recent Overflow']),
            scottishWaterRecord(['ASSET_ID' => 'CSO000010', 'OVERFLOW_STATUS_DESCRIPTION' => 'DA - No Data Available', 'RECEIVING_WATER' => 'Un-named watercourse']),
        ]]),
    ]);

    $result = app(SyncOverflowMonitors::class)->handle();

    expect($result['feeds'])->toBe(2)->and($result['failed'])->toBe([]);

    $monitors = OverflowMonitor::query()->where('company', 'Scottish Water')->get()->keyBy('external_id');

    expect($monitors->keys()->sort()->values()->all())->toBe(['SWCSO000007', 'SWCSO000008', 'SWCSO000009', 'SWCSO000010'])
        ->and($monitors['SWCSO000007']->status)->toBe(OverflowStatus::NotDischarging)
        ->and($monitors['SWCSO000007']->receiving_watercourse)->toBe('River Tweed')
        ->and($monitors['SWCSO000008']->status)->toBe(OverflowStatus::Discharging)
        ->and($monitors['SWCSO000008']->spillEvents()->sole()->ended_at)->toBeNull()
        ->and($monitors['SWCSO000009']->status)->toBe(OverflowStatus::NotDischarging)
        ->and($monitors['SWCSO000009']->spillEvents()->sole()->ended_at->toIso8601String())->toBe('2026-10-15T09:30:00+00:00')
        ->and($monitors['SWCSO000010']->status)->toBe(OverflowStatus::Offline)
        ->and($monitors['SWCSO000010']->receiving_watercourse)->toBeNull()
        ->and($monitors['SWCSO000007']->source_updated_at->getTimestampMs())->toBe(1792098080000);
});

it('reads Stream feeds whose field names are in another case, and pages through services that return fewer per page', function () {
    config(['services.overflow_feeds' => [
        'south_west_water' => ['name' => 'South West Water', 'driver' => 'stream', 'url' => 'https://south-west.test/FeatureServer/0'],
        'anglian_water' => ['name' => 'Anglian Water', 'driver' => 'stream', 'url' => 'https://anglian.test/FeatureServer/0'],
    ]]);
    $anglianPage = fn (int $from, int $count) => collect(range($from, $from + $count - 1))
        ->map(fn (int $number) => arcGisFeature(['Id' => sprintf('AWS%05d', $number), 'Company' => 'Anglian Water Services']))->all();

    Http::fake([
        'south-west.test/*' => Http::response(['features' => [['attributes' => [
            'Id' => 'SBB00407', 'company' => 'South West Water', 'status' => 1, 'statusStart' => 1791274345000,
            'latestEventStart' => 1791274345000, 'latestEventEnd' => null, 'latitude' => 50.514064, 'longitude' => -4.826458,
            'receivingWaterCourse' => 'CAMEL ESTUARY (E)', 'lastUpdated' => 1792270800000,
        ]]]]),
        // The service stops at 1,000 a page, below the 2,000 asked for, and says there is more.
        'anglian.test/*' => fn ($request) => (int) ($request->data()['resultOffset'] ?? 0) === 0
            ? Http::response(['features' => $anglianPage(1, 1000), 'exceededTransferLimit' => true])
            : Http::response(['features' => $anglianPage(1001, 439), 'exceededTransferLimit' => false]),
    ]);

    app(SyncOverflowMonitors::class)->handle();

    $southWest = OverflowMonitor::query()->where('external_id', 'SBB00407')->sole();

    expect($southWest->status)->toBe(OverflowStatus::Discharging)
        ->and($southWest->receiving_watercourse)->toBe('CAMEL ESTUARY (E)')
        ->and($southWest->latitude)->toBe(50.514064)
        ->and($southWest->spillEvents()->sole()->ended_at)->toBeNull()
        ->and(OverflowMonitor::query()->where('company', 'Anglian Water Services')->count())->toBe(1439);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'anglian.test') && ($request->data()['resultOffset'] ?? null) === 1000);
});

/**
 * One overflow from Dŵr Cymru Welsh Water's storm overflow map feature service.
 *
 * @param  array<string, mixed>  $overrides
 * @return array{attributes: array<string, mixed>, geometry: array{x: float, y: float}}
 */
function welshWaterFeature(array $overrides = []): array
{
    return [
        'attributes' => array_merge([
            'DCWW_ID' => 'DCWW1096',
            'status' => 'Overflow Not Operating',
            'start_date_time_discharge' => '2026-10-15T23:15:00',
            'stop_date_time_discharge' => '2026-10-15T23:30:00',
            'Receiving_Water' => 'Cyt Bach',
            'EditDate' => 1792266012452,
        ], $overrides),
        'geometry' => ['x' => -4.129494606604582, 'y' => 52.92994061011652],
    ];
}

it('reads Dŵr Cymru Welsh Water\'s storm overflow map, whose statuses are words and times are UK local time', function () {
    $this->travelTo('2026-10-17 22:00:00');
    config(['services.overflow_feeds' => [
        'welsh_water' => ['name' => 'Dŵr Cymru Welsh Water', 'driver' => 'welsh_water', 'url' => 'https://welsh-water.test/FeatureServer/0', 'updated_on_change' => true],
    ]]);
    Http::fake([
        'welsh-water.test/*' => Http::response(['features' => [
            welshWaterFeature(),
            welshWaterFeature(['DCWW_ID' => 'DCWW1003', 'status' => 'Overflow Operating', 'start_date_time_discharge' => '2026-10-09T07:00:00', 'stop_date_time_discharge' => null]),
            welshWaterFeature(['DCWW_ID' => 'DCWW1027', 'status' => 'Overflow Not Operating (Has in the last 24 hours)']),
            welshWaterFeature(['DCWW_ID' => 'DCWW1246', 'status' => 'Under Investigation', 'start_date_time_discharge' => null, 'stop_date_time_discharge' => null]),
            welshWaterFeature(['DCWW_ID' => null]),
        ]]),
    ]);

    $result = app(SyncOverflowMonitors::class)->handle();
    $monitors = OverflowMonitor::query()->get()->keyBy('external_id');

    expect($result['failed'])->toBe([])
        ->and($monitors->keys()->sort()->values()->all())->toBe(['DCWW1003', 'DCWW1027', 'DCWW1096', 'DCWW1246'])
        ->and($monitors['DCWW1096']->company)->toBe('Dŵr Cymru Welsh Water')
        ->and($monitors['DCWW1096']->status)->toBe(OverflowStatus::NotDischarging)
        ->and($monitors['DCWW1096']->latitude)->toEqualWithDelta(52.929941, 0.000001)
        ->and($monitors['DCWW1096']->longitude)->toEqualWithDelta(-4.129495, 0.000001)
        ->and($monitors['DCWW1096']->receiving_watercourse)->toBe('Cyt Bach')
        // 23:15 British Summer Time.
        ->and($monitors['DCWW1096']->spillEvents()->sole()->started_at->toIso8601String())->toBe('2026-10-15T22:15:00+00:00')
        ->and($monitors['DCWW1096']->spillEvents()->sole()->ended_at->toIso8601String())->toBe('2026-10-15T22:30:00+00:00')
        ->and($monitors['DCWW1003']->status)->toBe(OverflowStatus::Discharging)
        ->and($monitors['DCWW1003']->status_started_at->toIso8601String())->toBe('2026-10-09T06:00:00+00:00')
        ->and($monitors['DCWW1003']->spillEvents()->sole()->ended_at)->toBeNull()
        ->and($monitors['DCWW1027']->status)->toBe(OverflowStatus::NotDischarging)
        ->and($monitors['DCWW1246']->status)->toBe(OverflowStatus::Offline)
        ->and($monitors['DCWW1246']->spillEvents()->exists())->toBeFalse()
        // The record was last edited the same day, so the read confirms it (updated_on_change).
        ->and($monitors['DCWW1096']->source_updated_at->toDateTimeString())->toBe('2026-10-17 22:00:00');
});

it('keeps Scottish Water\'s overflow names and the overflow before the latest, so one between polls is not lost', function () {
    pollOnly('scottish_water');
    Http::fake(['api.scottishwater.co.uk/*' => Http::response(['last_updated' => 1792098080801, 'results' => [
        scottishWaterRecord([
            'ASSET_NAME' => 'CROSSHOUSE, 9 KILMAURS ROAD CSO',
            'OVERFLOW_START_DATETIME_PREVIOUS' => '2026-10-15T02:05:00.000Z',
            'OVERFLOW_END_DATETIME_PREVIOUS' => '2026-10-15T09:15:00.000Z',
        ]),
    ]])]);

    $result = app(SyncOverflowMonitors::class)->handle();
    $monitor = OverflowMonitor::query()->sole();

    expect($monitor->name)->toBe('Crosshouse, 9 Kilmaurs Road CSO')
        ->and($result['events_opened'])->toBe(2)
        ->and($monitor->spillEvents()->get()->map(fn (SpillEvent $event) => [$event->started_at->toIso8601String(), $event->ended_at?->toIso8601String()])->all())->toBe([
            ['2026-10-15T09:20:00+00:00', '2026-10-15T09:30:00+00:00'],
            ['2026-10-15T02:05:00+00:00', '2026-10-15T09:15:00+00:00'],
        ]);

    // Read again, nothing is added twice.
    expect(app(SyncOverflowMonitors::class)->handle()['events_opened'])->toBe(0);
});

it('records when a monitor goes offline and when it comes back, and keeps a name a nameless feed does not give', function () {
    $this->freezeTime();
    OverflowMonitor::factory()->create(['external_id' => 'YWS00001', 'return_name' => 'Ellerton CSO']);
    $status = -1;
    Http::fake(['services-eu1.arcgis.com/*' => function () use (&$status) {
        return Http::response(['features' => [arcGisFeature(['Status' => $status])]]);
    }]);

    app(SyncOverflowMonitors::class)->handle();
    app(SyncOverflowMonitors::class)->handle();
    $monitor = OverflowMonitor::query()->sole();

    expect($monitor->offlinePeriods()->sole())
        ->started_at->toDateTimeString()->toBe(now()->toDateTimeString())
        ->ended_at->toBeNull();

    $this->travel(2)->hours();
    $status = 0;
    app(SyncOverflowMonitors::class)->handle();

    expect($monitor->offlinePeriods()->sole()->ended_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($monitor->fresh()->displayName())->toBe('Ellerton CSO');
});

it('syncs a monitor a feed lists twice once, from its latest record, rather than failing the feed', function () {
    Http::fake(['services-eu1.arcgis.com/*' => Http::response(['features' => [
        arcGisFeature(['Id' => 'YWS00528', 'Status' => 1, 'LatestEventEnd' => null, 'LastUpdated' => 1760002000000]),
        arcGisFeature(['Id' => 'YWS00528', 'Status' => 0, 'LastUpdated' => 1760001000000]),
        arcGisFeature(['Id' => 'YWS00529']),
    ]])]);

    $result = app(SyncOverflowMonitors::class)->handle();

    expect($result['failed'])->toBe([])
        ->and($result['monitors'])->toBe(2)
        ->and(OverflowMonitor::query()->where('external_id', 'YWS00528')->sole()->status)->toBe(OverflowStatus::Discharging);
});
