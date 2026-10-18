<?php

use App\Actions\Rivers\FetchRiverCentreline;
use App\Models\River;

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
