<?php

use App\Models\GaugeReading;
use App\Models\GaugeStation;
use App\Models\OverflowMonitor;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;

it('lists published spots', function () {
    $river = River::factory()->create(['slug' => 'wharfe']);
    Spot::factory()->create(['river_id' => $river->id, 'slug' => 'otley']);
    Spot::factory()->unpublished()->create(['river_id' => $river->id, 'slug' => 'hidden']);

    $this->getJson(route('api.v1.spots.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'otley')
        ->assertJsonPath('data.0.river.slug', 'wharfe');
});

it('returns full conditions for a spot', function () {
    $river = River::factory()->create();
    $spot = Spot::factory()->create(['river_id' => $river->id, 'slug' => 'ilkley', 'river_metres' => 50000]);
    $monitor = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 48000]);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => now()->subHours(5), 'ended_at' => now()->subHours(4)]);

    $this->getJson(route('api.v1.spots.show', $spot))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=300, public')
        ->assertJsonPath('data.spot.slug', 'ilkley')
        ->assertJsonPath('data.spills.recency', 'within_24_hours')
        ->assertJsonPath('data.spills.last_48_hours', 1)
        ->assertJsonPath('data.level', null)
        ->assertJsonPath('data.upstream_monitors.0.id', $monitor->external_id)
        ->assertJsonPath('data.upstream_monitors.0.river.slug', $river->slug)
        ->assertJsonCount(30, 'data.history.days')
        ->assertJsonPath('data.history.events.0.monitor', $monitor->external_id)
        ->assertJsonPath('data.history.events.0.duration_minutes', 60)
        ->assertJsonPath('data.upstream_monitors.0.distance_upstream_metres', 2000)
        ->assertJsonPath('data.upstream_monitors.0.latest_event.duration_minutes', 60);
});

it('returns river overviews', function () {
    $river = River::factory()->create(['slug' => 'aire']);
    Spot::factory()->create(['river_id' => $river->id]);
    OverflowMonitor::factory()->discharging()->create(['river_id' => $river->id, 'river_metres' => 1000]);

    $this->getJson(route('api.v1.rivers.show', $river))
        ->assertOk()
        ->assertJsonPath('data.slug', 'aire')
        ->assertJsonPath('data.monitor_count', 1)
        ->assertJsonPath('data.discharging_now', 1)
        ->assertJsonCount(1, 'data.spots');
});

it('404s for unknown spots as json', function () {
    $this->getJson(route('api.v1.spots.show', 'nowhere'))->assertNotFound();
});

it('flags stale overflow and gauge data', function () {
    $river = River::factory()->create();
    $station = GaugeStation::factory()->create(['river_id' => $river->id]);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'gauge_station_id' => $station->id, 'river_metres' => 50000]);
    OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 48000, 'source_updated_at' => now()->subHours(8)]);
    GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => now()->subHours(8)]);

    $this->getJson(route('api.v1.spots.show', $spot))
        ->assertOk()
        ->assertJsonPath('data.data_stale', true)
        ->assertJsonPath('data.level.stale', true);
});
