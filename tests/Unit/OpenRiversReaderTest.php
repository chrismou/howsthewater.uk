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
