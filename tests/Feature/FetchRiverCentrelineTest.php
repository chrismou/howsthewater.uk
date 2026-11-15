<?php

use App\Actions\Rivers\FetchRiverCentreline;
use App\Models\River;
use App\Services\Geo\BritishNationalGrid;

it('looks a river up in OS Open Rivers by its OS name where the site names it differently', function () {
    $path = openRiversFixture([
        ['name' => 'River Avon', 'start' => 'a', 'end' => 'b', 'points' => [[400000, 450000], [401000, 450000]]],
        ['name' => 'River Avon', 'start' => 'b', 'end' => 'c', 'points' => [[401000, 450000], [402000, 450000]]],
    ]);
    $river = River::factory()->create(['name' => 'Bristol Avon', 'os_name' => 'River Avon', 'centreline' => null]);

    app(FetchRiverCentreline::class)->fromOpenRivers($river, $path);

    expect($river->fresh()->centreline)->toHaveCount(3)
        ->and($river->fresh()->length_metres)->toBeGreaterThan(1900);
});

it('carries a river on along the watercourse it continues along to the river it flows into', function () {
    $path = openRiversFixture([
        ['name' => 'River Irwell', 'start' => 'a', 'end' => 'b', 'points' => [[400000, 450000], [405000, 450000]]],
        ['name' => 'Manchester Ship Canal', 'start' => 'b', 'end' => 'c', 'points' => [[405000, 450000], [410000, 450000]]],
    ]);
    $mersey = River::factory()->create(['centreline' => [BritishNationalGrid::toLatLng(410000, 455000), BritishNationalGrid::toLatLng(410000, 445000)]]);
    $irwell = River::factory()->create(['name' => 'River Irwell', 'centreline' => null, 'flows_into_river_id' => $mersey->id, 'continues_along' => 'Manchester Ship Canal', 'mouth_latitude' => 53.946, 'mouth_longitude' => -1.925]);

    app(FetchRiverCentreline::class)->fromOpenRivers($irwell, $path);

    expect($irwell->fresh()->centreline)->toHaveCount(3)
        ->and($irwell->fresh()->length_metres)->toBeGreaterThan(9900);
});

it('fetches nothing for a river that continues on to one with no line yet, so the next run tries again', function () {
    $path = openRiversFixture([
        ['name' => 'River Irwell', 'start' => 'a', 'end' => 'b', 'points' => [[400000, 450000], [405000, 450000]]],
    ]);
    $mersey = River::factory()->create(['centreline' => null]);
    $irwell = River::factory()->create(['name' => 'River Irwell', 'centreline' => null, 'flows_into_river_id' => $mersey->id, 'continues_along' => 'Manchester Ship Canal', 'mouth_latitude' => 53.946, 'mouth_longitude' => -1.925]);

    expect(fn () => app(FetchRiverCentreline::class)->fromOpenRivers($irwell, $path))->toThrow(RuntimeException::class)
        ->and($irwell->fresh()->centreline)->toBeNull();
});
