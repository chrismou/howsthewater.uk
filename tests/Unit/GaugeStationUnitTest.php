<?php

use App\Models\GaugeStation;

it('shows stage readings as plain metres and keeps mAOD', function (?string $unit, string $label, bool $aboveDatum) {
    $station = new GaugeStation(['unit' => $unit]);

    expect($station->unitLabel())->toBe($label)
        ->and($station->isAboveOrdnanceDatum())->toBe($aboveDatum);
})->with([
    'stage in metres' => ['m', 'm', false],
    'metres above stage datum' => ['mASD', 'm', false],
    'above ordnance datum' => ['mAOD', 'mAOD', true],
    'unknown' => [null, 'm', false],
]);
