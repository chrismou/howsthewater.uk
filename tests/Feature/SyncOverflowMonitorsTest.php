<?php

use App\Actions\Ingest\SyncOverflowMonitors;
use App\Enums\OverflowStatus;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\SpillEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Region::factory()->create(['overflow_feeds' => ['yorkshire_water']]);
});

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

    expect($result)->toBe(['monitors' => 1, 'events_opened' => 1, 'events_closed' => 0, 'feeds' => 1, 'failed' => []]);

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

it('polls only the feeds a region needs and keeps going when one fails', function () {
    Sleep::fake();
    Region::factory()->create(['overflow_feeds' => ['northumbrian_water', 'united_utilities']]);

    Http::fake([
        'services-eu1.arcgis.com/1WqkK5cDKUbF0CkH/*' => Http::response(['features' => [arcGisFeature(['Id' => 'YWS00001'])]]),
        'services-eu1.arcgis.com/MSNNjkZ51iVh8yBj/*' => Http::response(['features' => [arcGisFeature(['Id' => 'NWL00001', 'Company' => 'Northumbrian Water Ltd'])]]),
        'services5.arcgis.com/*' => Http::response('Service unavailable', 503),
        '*' => Http::response(['features' => [arcGisFeature(['Id' => 'XXX00001'])]]),
    ]);

    $result = app(SyncOverflowMonitors::class)->handle();

    expect($result['feeds'])->toBe(2)
        ->and($result['failed'])->toBe(['United Utilities'])
        ->and(OverflowMonitor::query()->orderBy('external_id')->pluck('external_id')->all())->toBe(['NWL00001', 'YWS00001']);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'services1.arcgis.com'));
});

it('fails the ingest command when a feed fails', function () {
    Sleep::fake();
    Http::fake(['*' => Http::response('Service unavailable', 503)]);

    $this->artisan('ingest:overflows')
        ->expectsOutputToContain('Yorkshire Water feed failed')
        ->assertFailed();
});
