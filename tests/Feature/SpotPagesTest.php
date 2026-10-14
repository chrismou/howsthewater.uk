<?php

use App\Models\GaugeReading;
use App\Models\GaugeStation;
use App\Models\OverflowMonitor;
use App\Models\RainfallReading;
use App\Models\RainfallStation;
use App\Models\River;
use App\Models\Spot;

it('lists rivers and spots on the home page', function () {
    $river = River::factory()->create(['name' => 'River Wharfe', 'slug' => 'wharfe']);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Otley', 'slug' => 'otley']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('River Wharfe')
        ->assertSee('Otley')
        ->assertSee(route('spots.show', 'otley'));
});

it('shows a river page with its spots source to mouth', function () {
    $river = River::factory()->create(['slug' => 'aire']);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Castleford', 'river_metres' => 90000]);
    Spot::factory()->create(['river_id' => $river->id, 'name' => 'Skipton', 'river_metres' => 20000]);

    $this->get(route('rivers.show', $river))
        ->assertOk()
        ->assertSeeInOrder(['Skipton', 'Castleford']);
});

it('shows a spot page with upstream monitors and the spill summary', function () {
    $river = River::factory()->create();
    $spot = Spot::factory()->create(['river_id' => $river->id, 'river_metres' => 50000]);
    $monitor = OverflowMonitor::factory()->discharging()->create(['river_id' => $river->id, 'river_metres' => 45000]);

    $this->get(route('spots.show', $spot))
        ->assertOk()
        ->assertSee($spot->name)
        ->assertSee('Discharging upstream now')
        ->assertSee($monitor->external_id);
});

it('hides unpublished spots', function () {
    $spot = Spot::factory()->unpublished()->create();

    $this->get(route('spots.show', $spot))->assertNotFound();
    $this->get(route('home'))->assertDontSee($spot->name);
});

it('warns when monitor and gauge data are out of date', function () {
    $river = River::factory()->create();
    $station = GaugeStation::factory()->create(['river_id' => $river->id]);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'gauge_station_id' => $station->id, 'river_metres' => 50000]);
    OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 45000, 'source_updated_at' => now()->subHours(8)]);
    GaugeReading::factory()->create(['gauge_station_id' => $station->id, 'measured_at' => now()->subHours(8)]);

    $this->get(route('spots.show', $spot))
        ->assertOk()
        ->assertSee('Monitor data is out of date')
        ->assertSee('No reading from this gauge for 8 hours');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Monitor data is 8 hours old');
});

it('does not warn when data is fresh', function () {
    $river = River::factory()->create();
    $spot = Spot::factory()->create(['river_id' => $river->id, 'river_metres' => 50000]);
    OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 45000]);

    $this->get(route('spots.show', $spot))
        ->assertOk()
        ->assertDontSee('Monitor data is out of date');
});

it('shows rainfall at the nearest rain gauge', function () {
    $station = RainfallStation::factory()->create();
    $spot = Spot::factory()->create(['rainfall_station_id' => $station->id, 'river_metres' => null]);
    RainfallReading::factory()->create(['rainfall_station_id' => $station->id, 'measured_at' => now()->subMinutes(15), 'millimetres' => 3.25]);

    $this->get(route('spots.show', $spot))
        ->assertOk()
        ->assertSee('in the last 24 hours')
        ->assertSee('3.3 mm');

    $this->get(route('home'))->assertSee('3.3 mm rain in 24h');
});
