<?php

use App\Enums\SpillRecency;

it('says where the discharge is: upstream for spots, nearby for beaches and lakes', function (SpillRecency $recency) {
    expect(mb_strtolower($recency->label()))->toContain('upstream')
        ->and(mb_strtolower($recency->nearbyLabel()))->not->toContain('upstream');
})->with(collect(SpillRecency::cases())->reject(fn (SpillRecency $recency) => $recency === SpillRecency::NotCovered)->all());
