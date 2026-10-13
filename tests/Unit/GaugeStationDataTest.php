<?php

use App\Data\GaugeStationData;

function environmentAgencyStation(array $measures): array
{
    return [
        'stationReference' => 'L1708',
        'label' => 'Kirkstall Abbey',
        'riverName' => 'River Aire',
        'town' => 'Leeds',
        'lat' => 53.82,
        'long' => -1.60,
        'status' => 'http://environment.data.gov.uk/flood-monitoring/def/core/statusActive',
        'stageScale' => ['typicalRangeLow' => 0.2, 'typicalRangeHigh' => 1.1],
        'measures' => $measures,
    ];
}

it('prefers the stage level measure that has a unit', function () {
    $station = GaugeStationData::fromEnvironmentAgency(environmentAgencyStation([
        ['@id' => 'http://example/id/measures/L1708-level-stage-i-15_min----', 'parameter' => 'level', 'qualifier' => 'Stage', 'unitName' => '---'],
        ['@id' => 'http://example/id/measures/L1708-level-stage-i-15_min-m', 'parameter' => 'level', 'qualifier' => 'Stage', 'unitName' => 'm'],
    ]));

    expect($station->measureId)->toBe('L1708-level-stage-i-15_min-m')
        ->and($station->typicalRangeHigh)->toBe(1.1)
        ->and($station->isActive)->toBeTrue();
});

it('ignores non-stage measures', function () {
    $station = GaugeStationData::fromEnvironmentAgency(environmentAgencyStation([
        ['@id' => 'http://example/id/measures/L1708-flow-i-15_min-m3_s', 'parameter' => 'flow', 'qualifier' => 'Stage', 'unitName' => 'm3/s'],
        ['@id' => 'http://example/id/measures/L1708-level-downstage-i-15_min-m', 'parameter' => 'level', 'qualifier' => 'Downstream Stage', 'unitName' => 'm'],
    ]));

    expect($station->measureId)->toBeNull();
});

it('accepts a single measure object', function () {
    $station = GaugeStationData::fromEnvironmentAgency(environmentAgencyStation(
        ['@id' => 'http://example/id/measures/L1708-level-stage-i-15_min-m', 'parameter' => 'level', 'qualifier' => 'Stage', 'unitName' => 'm'],
    ));

    expect($station->measureId)->toBe('L1708-level-stage-i-15_min-m');
});

it('prefers a stage in metres over one in mAOD and records the unit', function () {
    $station = GaugeStationData::fromEnvironmentAgency(environmentAgencyStation([
        ['@id' => 'http://example/id/measures/L1708-level-stage-i-15_min-mAOD', 'parameter' => 'level', 'qualifier' => 'Stage', 'unitName' => 'mAOD'],
        ['@id' => 'http://example/id/measures/L1708-level-stage-i-15_min-m', 'parameter' => 'level', 'qualifier' => 'Stage', 'unitName' => 'm'],
    ]));

    expect($station->measureId)->toBe('L1708-level-stage-i-15_min-m')
        ->and($station->unit)->toBe('m');
});

it('falls back to an mAOD stage when that is all the station has', function () {
    $station = GaugeStationData::fromEnvironmentAgency(environmentAgencyStation([
        ['@id' => 'http://example/id/measures/L1707A-level-stage-i-15_min-mAOD', 'parameter' => 'level', 'qualifier' => 'Stage', 'unitName' => 'mAOD'],
    ]));

    expect($station->measureId)->toBe('L1707A-level-stage-i-15_min-mAOD')
        ->and($station->unit)->toBe('mAOD');
});
