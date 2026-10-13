<?php

use App\Services\Osm\WayChainer;

it('joins unordered ways into one downstream polyline', function () {
    $first = [[54.00, -2.00], [53.99, -1.99]];
    $second = [[53.99, -1.99], [53.98, -1.98]];
    $third = [[53.98, -1.98], [53.97, -1.97]];

    $chain = WayChainer::chain([$third, $first, $second]);

    expect($chain)->toBe([[54.00, -2.00], [53.99, -1.99], [53.98, -1.98], [53.97, -1.97]]);
});

it('reverses a way drawn against the flow when most ways agree', function () {
    $first = [[54.00, -2.00], [53.99, -1.99]];
    $secondReversed = [[53.98, -1.98], [53.99, -1.99]];
    $third = [[53.98, -1.98], [53.97, -1.97]];

    $chain = WayChainer::chain([$first, $secondReversed, $third]);

    expect($chain)->toBe([[54.00, -2.00], [53.99, -1.99], [53.98, -1.98], [53.97, -1.97]]);
});

it('stops chaining when the nearest way is too far away', function () {
    $first = [[54.00, -2.00], [53.99, -1.99]];
    $farAway = [[53.50, -1.50], [53.49, -1.49]];

    $chain = WayChainer::chain([$first, $farAway]);

    expect($chain)->toBe($first);
});
