<?php

use App\Actions\Ingest\SyncOverflowMonitors;
use App\Enums\OverflowStatus;
use App\Models\OverflowMonitor;
use App\Models\SpillEvent;
use Illuminate\Support\Facades\Http;

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

    expect($result)->toBe(['monitors' => 1, 'events_opened' => 1, 'events_closed' => 0]);

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
