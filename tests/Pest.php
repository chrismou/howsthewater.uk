<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * The EA's bulk latest readings response, one reading per measure id.
 *
 * @param  array<string, float>  $values  Values keyed by measure id
 * @return array{items: list<array<string, mixed>>}
 */
function environmentAgencyLatestReadings(array $values, ?Carbon\CarbonInterface $at = null): array
{
    $at ??= now()->subMinutes(15);

    return ['items' => collect($values)->map(fn (float $value, string $measureId) => [
        'dateTime' => $at->toIso8601String(),
        'measure' => "http://environment.data.gov.uk/flood-monitoring/id/measures/{$measureId}",
        'value' => $value,
    ])->values()->all()];
}

/**
 * Encodes a polyline of British National Grid points (easting, northing)
 * as a GeoPackage LineString blob, for small local test fixtures.
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
 * @param  array<int, array{name: string, alternative?: string, start: string, end: string, points: array<int, array{0: float, 1: float}>}>  $links
 */
function openRiversFixture(array $links): string
{
    $path = tempnam(sys_get_temp_dir(), 'oprvrs').'.gpkg';
    $pdo = new PDO('sqlite:'.$path);
    $pdo->exec('CREATE TABLE watercourse_link (id TEXT, geometry BLOB, flow_direction TEXT, start_node TEXT, end_node TEXT, watercourse_name TEXT, watercourse_name_alternative TEXT)');
    $insert = $pdo->prepare('INSERT INTO watercourse_link VALUES (?, ?, ?, ?, ?, ?, ?)');

    foreach ($links as $index => $link) {
        $insert->execute(["link-{$index}", geoPackageLineString($link['points']), 'in direction', $link['start'], $link['end'], $link['name'], $link['alternative'] ?? null]);
    }

    return $path;
}
