<?php

use App\Services\Geo\Coordinates;

it('tells tall regions from wide ones on the ground', function () {
    // Preston to Carlisle by Workington to Kirkby Stephen: about 137 km tall and 88 km wide.
    $northWest = [[53.733, -2.877], [54.967, -3.325], [54.489, -3.578], [54.364, -2.219]];
    // Cricklade to Teddington: about 46 km tall and 140 km wide.
    $thamesValley = [[51.644, -1.934], [51.43, -0.33], [51.85, -1.2]];

    expect(Coordinates::isTallerThanWide($northWest))->toBeTrue()
        ->and(Coordinates::isTallerThanWide($thamesValley))->toBeFalse()
        ->and(Coordinates::isTallerThanWide([[54.0, -2.0]]))->toBeFalse();
});
