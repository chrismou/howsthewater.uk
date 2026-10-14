<?php

use App\Services\Geo\BritishNationalGrid;
use App\Services\OrdnanceSurvey\OpenRiversReader;

/**
 * Encodes a lat/lng polyline as a GeoPackage LineString blob in BNG,
 * using the inverse of the projection constants closely enough for a
 * small local test (points are given directly as easting/northing).
 *
 * @param  array<int, array{0: float, 1: float}>  $points  [easting, northing] pairs
 */
function geoPackageLineString(array $points): string
{
    $header = 'GP'.chr(0).chr(0b00000001).pack('V', 27700);
    $wkb = chr(1).pack('V', 2).pack('V', count($points));

    foreach ($points as [$easting, $northing]) {
        $wkb .= pack('e', $easting).pack('e', $northing);
    }

    return $header.$wkb;
}

/**
 * @param  array<int, array{name: string, start: string, end: string, points: array<int, array{0: float, 1: float}>}>  $links
 */
function openRiversFixture(array $links): string
{
    $path = tempnam(sys_get_temp_dir(), 'oprvrs').'.gpkg';
    $pdo = new PDO('sqlite:'.$path);
    $pdo->exec('CREATE TABLE watercourse_link (id TEXT, geometry BLOB, flow_direction TEXT, start_node TEXT, end_node TEXT, watercourse_name TEXT)');
    $insert = $pdo->prepare('INSERT INTO watercourse_link VALUES (?, ?, ?, ?, ?, ?)');

    foreach ($links as $index => $link) {
        $insert->execute(["link-{$index}", geoPackageLineString($link['points']), 'in direction', $link['start'], $link['end'], $link['name']]);
    }

    return $path;
}

it('chains the links of a named watercourse from source to mouth', function () {
    $path = openRiversFixture([
        ['name' => 'Test Beck', 'start' => 'b', 'end' => 'c', 'points' => [[401000, 450000], [402000, 450000]]],
        ['name' => 'Test Beck', 'start' => 'a', 'end' => 'b', 'points' => [[400000, 450000], [401000, 450000]]],
    ]);

    $centreline = (new OpenRiversReader($path))->riverCentreline('Test Beck');

    expect($centreline)->toHaveCount(3)
        ->and($centreline[0][1])->toBeLessThan($centreline[2][1]);
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
