<?php

use App\Services\Geo\WatercourseName;

it('normalises qualifiers and casing', function (string $raw, string $expected, bool $tributary) {
    $parsed = WatercourseName::parse($raw);

    expect($parsed->name)->toBe($expected)
        ->and($parsed->isUnnamedTributary)->toBe($tributary)
        ->and($parsed->matches(ucwords($expected)))->toBeTrue();
})->with([
    ['River Aire', 'river aire', false],
    ['River Aire (Via Public Sewer And Common Screen)', 'river aire', false],
    ['River Aire (Tidal)', 'river aire', false],
    ['Bradford Beck (Culverted)', 'bradford beck', false],
    ['Trib Of River Aire', 'river aire', true],
    ['Culverted Trib Of River Aire', 'river aire', true],
    ['Tributary of River Aire', 'river aire', true],
    ['Trib Of River Aire (Culverted)', 'river aire', true],
    ['Peasholm Beck To North Sea (North Bay)', 'peasholm beck to north sea', false],
]);

it('returns null for empty or placeholder names', function (?string $raw) {
    expect(WatercourseName::parse($raw))->toBeNull();
})->with([null, '', '  ', '#N/A']);
