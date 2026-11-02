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
        'stageScale' => ['typicalRangeLow' => 0.2, 'typicalRangeHigh' => 1.1, 'maxOnRecord' => ['dateTime' => '2024-01-07T15:45:00', 'value' => 7.54]],
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

it('reads the highest level on record as UK local time, and does without one', function () {
    $station = GaugeStationData::fromEnvironmentAgency(environmentAgencyStation([]));

    expect($station->recordLevel)->toBe(7.54)
        ->and($station->recordLevelAt->toIso8601String())->toBe('2024-01-07T15:45:00+00:00')
        ->and($station->recordLevelKnownToMonth)->toBeFalse();

    $summer = GaugeStationData::fromEnvironmentAgency([...environmentAgencyStation([]), 'stageScale' => ['maxOnRecord' => ['dateTime' => '2007-06-25T18:00:00', 'value' => 3.2]]]);
    $none = GaugeStationData::fromEnvironmentAgency([...environmentAgencyStation([]), 'stageScale' => ['typicalRangeLow' => 0.2]]);

    expect($summer->recordLevelAt->toIso8601String())->toBe('2007-06-25T17:00:00+00:00')
        ->and($none->recordLevel)->toBeNull()
        ->and($none->recordLevelAt)->toBeNull();
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

it('keeps the station\'s River Levels on the Internet id for its GOV.UK page', function () {
    $station = GaugeStationData::fromEnvironmentAgency([...environmentAgencyStation([]), 'RLOIid' => '8276']);

    expect($station->rloiId)->toBe('8276')
        ->and(GaugeStationData::fromEnvironmentAgency(environmentAgencyStation([]))->rloiId)->toBeNull();
});
