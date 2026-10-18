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
    ['THE RIVER WENSUM', 'river wensum', false],
    ['Tidal River Great Ouse', 'river great ouse', false],
    ['River Wid NT', 'river wid', false],
    ['Trib River Nene', 'river nene', true],
    ['Unknown trib. River Nene', 'river nene', true],
    ['TRIBUTARY RIVER THET', 'river thet', true],
    ['TRIB OF THE RIVER CROUCH', 'river crouch', true],
    ['Trib. of River Nene', 'river nene', true],
    ['Unknown trib of River Nene', 'river nene', true],
    ['Tidal Trib River Nene', 'river nene', true],
    ['The Tidal River Great Ouse', 'river great ouse', false],
    ['River Wid NT (Culverted)', 'river wid', false],
    // Only a separate "NT" is dropped, never the end of a name.
    ['River Trent', 'river trent', false],
    ['River Brent', 'river brent', false],
    ['Tributary River Thet NT', 'river thet', true],
    ['TRIB OF R.DART(E) VIA SW SEWER', 'river dart', true],
    ['TRIB O RIV.TEIGN VIA SW SEWER', 'river teign', true],
    ['A TRIBUTARY OF THE RIVER OTTER', 'river otter', true],
    ['R CULM (S)', 'river culm', false],
    ['R. MOLE (S)', 'river mole', false],
    ['RIVER TAMAR VIASWS (ESTUARINE)', 'river tamar', false],
    ['RIVER AVON [BLACK ROCKS] SWS', 'river avon', false],
    ['RIVER AVON[BLACK ROCKS](E)', 'river avon', false],
    ['Bristol Avon (By Bk to Netham W', 'bristol avon', false],
    ['TRIB OF BRISLINGTON BROOK SWS', 'brislington brook', true],
    ['RIVER KENWYN[S]', 'river kenwyn', false],
    ['UN-NAMED TRIBUTARY OF THE RIVER ADUR', 'river adur', true],
    ['AN UN-NAMED TRIBUTARY OF THE RIVER OUSE', 'river ouse', true],
    ['UNAMED TRIBUTARY - RIVER OUSE', 'river ouse', true],
    ['UNNAMED TRIBUTARY - RIVER HAMBLE', 'river hamble', true],
    ['River Avon [Sherston', 'river avon', false],
    // Only "R" on its own is short for river.
    ['Red River', 'red river', false],
    ['Rea Brook', 'rea brook', false],
]);

it('returns null for empty or placeholder names', function (?string $raw) {
    expect(WatercourseName::parse($raw))->toBeNull();
})->with([null, '', '  ', '#N/A']);
