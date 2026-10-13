<?php

use App\Services\Geo\PolylineProjector;

it('rejects polylines with fewer than two points', function () {
    new PolylineProjector([[53.9, -1.7]]);
})->throws(InvalidArgumentException::class);

it('measures the length of a polyline', function () {
    $projector = new PolylineProjector([
        [53.900, -1.700],
        [53.900, -1.600],
    ]);

    // One tenth of a degree of longitude at 53.9N is roughly 6.56 km.
    expect($projector->lengthMetres())->toBeBetween(6500, 6620);
});

it('projects a point onto the nearest part of the line and reports distance along it', function () {
    $projector = new PolylineProjector([
        [53.900, -1.700],
        [53.900, -1.600],
        [53.900, -1.500],
    ]);

    $projection = $projector->project(53.901, -1.650);

    expect($projection->distanceAlongMetres)->toBeBetween(3200, 3350)
        ->and($projection->offsetMetres)->toBeBetween(100, 120);
});

it('clamps points beyond the end of the line to the last vertex', function () {
    $projector = new PolylineProjector([
        [53.900, -1.700],
        [53.900, -1.600],
    ]);

    $projection = $projector->project(53.900, -1.550);

    expect($projection->distanceAlongMetres)->toBe($projector->lengthMetres());
});
