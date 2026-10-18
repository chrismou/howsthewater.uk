<?php

use App\Services\Geo\BritishNationalGrid;
use App\Services\OrdnanceSurvey\OpenRiversReader;

it('chains the links of a named watercourse from source to mouth', function () {
    $path = openRiversFixture([
        ['name' => 'Test Beck', 'start' => 'b', 'end' => 'c', 'points' => [[401000, 450000], [402000, 450000]]],
        ['name' => 'Test Beck', 'start' => 'a', 'end' => 'b', 'points' => [[400000, 450000], [401000, 450000]]],
    ]);

    $centreline = (new OpenRiversReader($path))->riverCentreline('Test Beck');

    expect($centreline)->toHaveCount(3)
        ->and($centreline[0][1])->toBeLessThan($centreline[2][1]);
});

it('finds a river by its alternative name when OS lists the Welsh name first', function () {
    $path = openRiversFixture([
        ['name' => 'Afon Hafren', 'alternative' => 'River Severn', 'start' => 'a', 'end' => 'b', 'points' => [[400000, 450000], [401000, 450000]]],
        ['name' => 'Afon Hafren', 'alternative' => 'River Severn', 'start' => 'b', 'end' => 'c', 'points' => [[401000, 450000], [402000, 450000]]],
    ]);

    expect((new OpenRiversReader($path))->riverCentreline('River Severn'))->toHaveCount(3);
});

it('joins the nearest piece first so a river broken into many pieces is not cut short', function () {
    $path = openRiversFixture([
        // Listed out of order: the piece furthest upstream comes first.
        ['name' => 'River Test', 'start' => 'e', 'end' => 'f', 'points' => [[404000, 450000], [406500, 450000]]],
        ['name' => 'River Test', 'start' => 'c', 'end' => 'd', 'points' => [[407000, 450000], [409500, 450000]]],
        ['name' => 'River Test', 'start' => 'a', 'end' => 'b', 'points' => [[410000, 450000], [412000, 450000]]],
    ]);

    $centreline = (new OpenRiversReader($path))->riverCentreline('River Test', mouthHint: BritishNationalGrid::toLatLng(412000, 450000));

    expect($centreline)->toHaveCount(6)
        ->and($centreline[0])->toEqualWithDelta(BritishNationalGrid::toLatLng(404000, 450000), 0.0001);
});

it('picks the same-named watercourse whose mouth is nearest the parent river', function () {
    $path = openRiversFixture([
        // A beck of the same name 50 km north, which must be ignored.
        ['name' => 'Test Beck', 'start' => 'x', 'end' => 'y', 'points' => [[400000, 500000], [401000, 500000]]],
        // The beck that actually meets the parent at (402000, 450000).
        ['name' => 'Test Beck', 'start' => 'a', 'end' => 'b', 'points' => [[402000, 452000], [402000, 451000]]],
        ['name' => 'Test Beck', 'start' => 'b', 'end' => 'c', 'points' => [[402000, 451000], [402000, 450000]]],
    ]);

    $parent = (new OpenRiversReader(openRiversFixture([
        ['name' => 'River Parent', 'start' => 'p', 'end' => 'c', 'points' => [[400000, 450000], [402000, 450000]]],
        ['name' => 'River Parent', 'start' => 'c', 'end' => 'q', 'points' => [[402000, 450000], [404000, 450000]]],
    ])))->riverCentreline('River Parent');

    $centreline = (new OpenRiversReader($path))->riverCentreline('Test Beck', $parent);

    $confluence = BritishNationalGrid::toLatLng(402000, 450000);

    expect($centreline)->toHaveCount(3)
        ->and(end($centreline))->toEqualWithDelta($confluence, 0.0001);
});

it('prefers a mouth hint to the parent river, for two same-named tributaries joining one parent', function () {
    $path = openRiversFixture([
        ['name' => 'Test Beck', 'start' => 'a', 'end' => 'b', 'points' => [[401000, 452000], [401000, 450000]]],
        ['name' => 'Test Beck', 'start' => 'x', 'end' => 'y', 'points' => [[403000, 452000], [403000, 450000]]],
    ]);
    $parent = [BritishNationalGrid::toLatLng(400000, 450000), BritishNationalGrid::toLatLng(404000, 450000)];

    $centreline = (new OpenRiversReader($path))->riverCentreline('Test Beck', $parent, BritishNationalGrid::toLatLng(403000, 450100));

    expect(end($centreline))->toEqualWithDelta(BritishNationalGrid::toLatLng(403000, 450000), 0.0001);
});

it('uses the mouth hint to pick between same-named rivers in different places', function () {
    $path = openRiversFixture([
        // A river of the same name 200 km south.
        ['name' => 'River Test', 'start' => 'x', 'end' => 'y', 'points' => [[400000, 250000], [410000, 250000]]],
        ['name' => 'River Test', 'start' => 'a', 'end' => 'b', 'points' => [[400000, 450000], [410000, 450000]]],
    ]);

    $centreline = (new OpenRiversReader($path))->riverCentreline('River Test', null, BritishNationalGrid::toLatLng(410500, 450200));

    expect($centreline)->toHaveCount(2)
        ->and($centreline[0])->toEqualWithDelta(BritishNationalGrid::toLatLng(400000, 450000), 0.0001);
});

it('joins fragments of the same river across a reservoir-sized gap', function () {
    $path = openRiversFixture([
        // Three fragments 1 km apart, flowing east, plus one far away upstream fragment to ignore.
        ['name' => 'River Test', 'start' => 'e', 'end' => 'f', 'points' => [[408000, 450000], [412000, 450000]]],
        ['name' => 'River Test', 'start' => 'a', 'end' => 'b', 'points' => [[400000, 450000], [403000, 450000]]],
        ['name' => 'River Test', 'start' => 'c', 'end' => 'd', 'points' => [[404000, 450000], [407000, 450000]]],
        ['name' => 'River Test', 'start' => 'p', 'end' => 'q', 'points' => [[380000, 450000], [390000, 450000]]],
    ]);

    $centreline = (new OpenRiversReader($path))->riverCentreline('River Test', null, BritishNationalGrid::toLatLng(412000, 450000));

    expect($centreline)->toHaveCount(6)
        ->and($centreline[0])->toEqualWithDelta(BritishNationalGrid::toLatLng(400000, 450000), 0.0001)
        ->and(end($centreline))->toEqualWithDelta(BritishNationalGrid::toLatLng(412000, 450000), 0.0001);
});

it('joins a piece running alongside from its nearest point, so the line never doubles back', function () {
    $path = openRiversFixture([
        // From the source to a break at (405000, 450000).
        ['name' => 'River Test', 'start' => 'a', 'end' => 'b', 'points' => [[400000, 450000], [405000, 450000]]],
        // A parallel channel of the same name, starting 2 km back up the valley and running on to the sea.
        ['name' => 'River Test', 'start' => 'x', 'end' => 'y', 'points' => [[403000, 450100], [405000, 450100], [409000, 450100]]],
    ]);

    $centreline = (new OpenRiversReader($path))->riverCentreline('River Test', null, BritishNationalGrid::toLatLng(409000, 450100));
    $longitudes = array_column($centreline, 1);

    expect($longitudes)->toBe(collect($longitudes)->sort()->values()->all())
        ->and(end($centreline))->toEqualWithDelta(BritishNationalGrid::toLatLng(409000, 450100), 0.0001);

    // The same when the parallel piece is the one joined downstream: the seed runs on past where it starts.
    $path = openRiversFixture([
        ['name' => 'River Test', 'start' => 'a', 'end' => 'b', 'points' => [[400000, 450000], [403000, 450000], [405000, 450000]]],
        ['name' => 'River Test', 'start' => 'x', 'end' => 'y', 'points' => array_map(fn (int $easting) => [$easting, 450100], range(403000, 409000, 500))],
    ]);

    $centreline = (new OpenRiversReader($path))->riverCentreline('River Test', null, BritishNationalGrid::toLatLng(409000, 450100));
    $longitudes = array_column($centreline, 1);

    expect($longitudes)->toBe(collect($longitudes)->sort()->values()->all())
        ->and($centreline[0])->toEqualWithDelta(BritishNationalGrid::toLatLng(400000, 450000), 0.0001)
        ->and(end($centreline))->toEqualWithDelta(BritishNationalGrid::toLatLng(409000, 450100), 0.0001);
});

it('never cuts the chain back to a short same-named piece beside it', function () {
    $path = openRiversFixture([
        ['name' => 'River Test', 'start' => 'a', 'end' => 'b', 'points' => array_map(fn (int $easting) => [$easting, 450000], range(400000, 409000, 500))],
        // A 400 m side fragment 100 m off the river, 2 km above its mouth.
        ['name' => 'River Test', 'start' => 'x', 'end' => 'y', 'points' => [[406800, 450100], [407200, 450100]]],
    ]);

    $centreline = (new OpenRiversReader($path))->riverCentreline('River Test', null, BritishNationalGrid::toLatLng(409000, 450000));

    $metres = (new App\Services\Geo\PolylineProjector($centreline))->lengthMetres();

    expect($centreline[0])->toEqualWithDelta(BritishNationalGrid::toLatLng(400000, 450000), 0.0001)
        ->and($metres)->toBeGreaterThanOrEqual(9000);
});

it('follows the main stem past a dead-end side channel carrying the same name', function () {
    $path = openRiversFixture([
        ['name' => 'River Test', 'start' => 'a', 'end' => 'b', 'points' => [[400000, 450000], [401000, 450000]]],
        // A short mill cut leaving the river at b and going nowhere.
        ['name' => 'River Test', 'start' => 'b', 'end' => 'cut', 'points' => [[401000, 450000], [401000, 452000]]],
        ['name' => 'River Test', 'start' => 'b', 'end' => 'c', 'points' => [[401000, 450000], [405000, 450000]]],
        ['name' => 'River Test', 'start' => 'c', 'end' => 'd', 'points' => [[405000, 450000], [409000, 450000]]],
    ]);

    $centreline = (new OpenRiversReader($path))->riverCentreline('River Test');

    expect($centreline)->toHaveCount(4)
        ->and(end($centreline))->toEqualWithDelta(BritishNationalGrid::toLatLng(409000, 450000), 0.0001);
});

it('follows the river through a braid with a link drawn against the flow', function () {
    $path = openRiversFixture([
        // The upper reach, which only meets the rest through a link drawn the wrong way round (j1 to k).
        ['name' => 'Test Beck', 'start' => 'u', 'end' => 'k', 'points' => [[397000, 450000], [399000, 450000]]],
        ['name' => 'Test Beck', 'start' => 'j1', 'end' => 'k', 'points' => [[401000, 450000], [399000, 450000]]],
        // A short side channel feeding the main stem, which must not become the source.
        ['name' => 'Test Beck', 'start' => 's', 'end' => 'j1', 'points' => [[401000, 451000], [401000, 450000]]],
        ['name' => 'Test Beck', 'start' => 'j1', 'end' => 'j2', 'points' => [[401000, 450000], [402000, 450000]]],
        ['name' => 'Test Beck', 'start' => 'j2', 'end' => 'm', 'points' => [[402000, 450000], [403000, 450000]]],
    ]);

    $centreline = (new OpenRiversReader($path))->riverCentreline('Test Beck', mouthHint: BritishNationalGrid::toLatLng(403500, 450000));

    expect($centreline[0])->toEqual(BritishNationalGrid::toLatLng(397000, 450000))
        ->and(end($centreline))->toEqual(BritishNationalGrid::toLatLng(403000, 450000));
});
