<?php

use App\Models\OverflowMonitor;
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
