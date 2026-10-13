<?php

use App\Services\Geo\BritishNationalGrid;

it('converts the Ordnance Survey worked example to WGS84', function () {
    [$lat, $lng] = BritishNationalGrid::toLatLng(429157.0, 623009.0);

    expect($lat)->toEqualWithDelta(55.5, 0.0001)
        ->and($lng)->toEqualWithDelta(-1.54, 0.0001);
});

it('places Leeds city centre where expected', function () {
    [$lat, $lng] = BritishNationalGrid::toLatLng(430000.0, 433500.0);

    expect($lat)->toEqualWithDelta(53.796, 0.002)
        ->and($lng)->toEqualWithDelta(-1.545, 0.002);
});
