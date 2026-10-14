<?php

use App\Enums\BathingWaterClassification;
use App\Enums\BathingWaterKind;
use App\Enums\PollutionRisk;
use App\Models\BathingWater;
use App\Models\GaugeStation;
use App\Models\Lake;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function bathingWaterItem(string $id, string $name, string $type, array $districts, float $lat, float $lng): array
{
    return [
        'eubwidNotation' => $id,
        'name' => ['_value' => $name, '_lang' => 'en'],
        'type' => ['http://environment.data.gov.uk/def/bathing-water/BathingWater', "http://environment.data.gov.uk/def/bathing-water/{$type}"],
        'district' => array_map(fn ($district) => ['name' => ['_value' => $district]], $districts),
        'appointedSewerageUndertaker' => ['name' => ['_value' => 'Yorkshire Water Services Limited']],
        'samplingPoint' => ['_about' => 'http://location.data.gov.uk/so/ef/SamplingPoint/bwsp.eaew/'.substr($id, -5), 'lat' => $lat, 'long' => $lng],
        'waterQualityImpactedByHeavyRain' => true,
        'latestComplianceAssessment' => ['complianceClassification' => ['name' => ['_value' => 'Good']], 'sampleYear' => ['ordinalYear' => 2025]],
        'latestRiskPrediction' => ['riskLevel' => ['name' => ['_value' => 'increased']], 'expiresAt' => ['_value' => '2026-07-02T08:29:00']],
        'latestProfile' => ['bathingWaterDescription' => ['_value' => "{$name} is a sandy beach."], 'seasonStartDate' => ['_value' => '2026-05-15'], 'seasonFinishDate' => ['_value' => '2026-09-30']],
        'latestSampleAssessment' => [
            'sampleDateTime' => 'http://reference.data.gov.uk/id/gregorian-instant/2026-07-01T10:40:00',
            'escherichiaColiCount' => 27,
            'escherichiaColiQualifier' => ['countQualifierNotation' => '='],
            'intestinalEnterococciCount' => 10,
            'intestinalEnterococciQualifier' => ['countQualifierNotation' => '<'],
        ],
    ];
}

/**
 * @param  array<int, array<string, mixed>>  $sites
 */
function fakeBathingWaterApi(array $sites): void
{
    Http::fake([
        'environment.data.gov.uk/doc/bathing-water.json*' => Http::response(['result' => ['items' => $sites]]),
        'environment.data.gov.uk/doc/bathing-water-quality/in-season/sample.json*' => Http::response(['result' => ['items' => [
            ['sampleDateTime' => ['inXSDDateTime' => ['_value' => '2026-06-24T09:00:00']], 'escherichiaColiCount' => 310, 'escherichiaColiQualifier' => ['countQualifierNotation' => '=']],
            ['sampleDateTime' => ['inXSDDateTime' => ['_value' => '2026-07-01T10:40:00']], 'escherichiaColiCount' => 27, 'escherichiaColiQualifier' => ['countQualifierNotation' => '=']],
        ]]]),
        'environment.data.gov.uk/data/bathing-water-quality/compliance-rBWD/*' => Http::response(['result' => ['items' => [
            ['sampleYear' => ['ordinalYear' => 2024], 'complianceClassification' => ['name' => ['_value' => 'Sufficient']]],
            ['sampleYear' => ['ordinalYear' => 2025], 'complianceClassification' => ['name' => ['_value' => 'Good']]],
        ]]]),
        'environment.data.gov.uk/flood-monitoring/id/stations/*' => Http::response(['items' => [
            'stationReference' => '735225', 'label' => 'Far Sawrey', 'riverName' => 'Windermere', 'lat' => 54.35, 'long' => -2.94,
            'status' => 'http://environment.data.gov.uk/flood-monitoring/def/core/statusActive',
            'measures' => [['@id' => 'http://x/id/measures/735225-level-stage-i-15_min-m', 'parameter' => 'level', 'qualifier' => 'Stage', 'unitName' => 'm', 'period' => 900]],
        ]]),
    ]);
}

it('tracks beaches in a region, lake sites on a lake and river sites on a spot, and skips the rest', function () {
    $yorkshire = Region::factory()->create(['slug' => 'yorkshire', 'bathing_water_districts' => ['North Yorkshire']]);
    $windermere = Lake::factory()->create(['region_id' => $yorkshire->id, 'name' => 'Windermere', 'slug' => 'windermere']);
    $spot = Spot::factory()->create(['river_id' => River::factory()->create(['region_id' => $yorkshire->id])->id, 'latitude' => 53.9319, 'longitude' => -1.8162]);

    fakeBathingWaterApi([
        bathingWaterItem('uke0000-01000', 'Scarborough South Bay', 'CoastalBathingWater', ['North Yorkshire'], 54.28, -0.40),
        bathingWaterItem('uke0000-02000', 'Saltburn', 'CoastalBathingWater', ['Redcar and Cleveland'], 54.58, -0.97),
        bathingWaterItem('uke0000-03000', 'Windermere, Fellfoot', 'LakeBathingWater', ['Westmorland and Furness'], 54.27, -2.95),
        bathingWaterItem('uke0000-04000', 'Wharfe at Cromwheel, Ilkley', 'RiverBathingWater', [], 53.9320, -1.8150),
        bathingWaterItem('uke0000-05000', 'Distant River', 'RiverBathingWater', [], 52.0, -2.0),
    ]);

    $this->artisan('ingest:bathing-waters')->assertSuccessful();

    expect(BathingWater::query()->orderBy('name')->pluck('name')->all())->toBe(['Scarborough South Bay', 'Wharfe at Cromwheel, Ilkley', 'Windermere, Fellfoot']);

    $beach = BathingWater::query()->where('slug', 'scarborough-south-bay')->sole();

    expect($beach->kind)->toBe(BathingWaterKind::Coastal)
        ->and($beach->region_id)->toBe($yorkshire->id)
        ->and($beach->sewerage_undertaker)->toBe('Yorkshire Water')
        ->and($beach->classification)->toBe(BathingWaterClassification::Good)
        ->and($beach->risk_level)->toBe(PollutionRisk::Increased)
        ->and($beach->risk_expires_at->toIso8601String())->toBe('2026-07-02T07:29:00+00:00')
        ->and($beach->sampling_point)->toBe('01000')
        ->and($beach->samples()->pluck('e_coli')->all())->toBe([27, 310])
        ->and($beach->classifications()->pluck('year')->all())->toBe([2025, 2024])
        ->and($beach->history_fetched_at)->not->toBeNull();

    expect(BathingWater::query()->where('name', 'Windermere, Fellfoot')->value('lake_id'))->toBe($windermere->id)
        ->and(BathingWater::query()->where('name', 'like', 'Wharfe%')->value('spot_id'))->toBe($spot->id);
});

it('links lakes to their level gauge', function () {
    $lake = Lake::factory()->create(['gauge_reference' => '735225']);
    fakeBathingWaterApi([]);

    $this->artisan('ingest:bathing-waters')->assertSuccessful();

    expect($lake->fresh()->gaugeStation->external_id)->toBe('735225')
        ->and(GaugeStation::query()->sole()->measure_id)->toBe('735225-level-stage-i-15_min-m');
});

it('logs an error and fails when a site history fetch fails', function () {
    Region::factory()->create(['bathing_water_districts' => ['North Yorkshire']]);
    Log::spy();

    Http::fake([
        'environment.data.gov.uk/doc/bathing-water.json*' => Http::response(['result' => ['items' => [
            bathingWaterItem('uke0000-01000', 'Scarborough South Bay', 'CoastalBathingWater', ['North Yorkshire'], 54.28, -0.40),
        ]]]),
        'environment.data.gov.uk/*' => Http::response('', 500),
    ]);

    $this->artisan('ingest:bathing-waters')->assertFailed();

    expect(BathingWater::query()->sole()->history_fetched_at)->toBeNull();
    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_starts_with($message, 'Bathing water history failed for Scarborough South Bay'));
});

it('gives a second site with the same name in a region its own slug', function () {
    Region::factory()->create(['bathing_water_districts' => ['North Yorkshire']]);

    fakeBathingWaterApi([
        bathingWaterItem('uke0000-01000', 'Filey', 'CoastalBathingWater', ['North Yorkshire'], 54.21, -0.28),
        bathingWaterItem('uke0000-02000', 'Filey', 'CoastalBathingWater', ['North Yorkshire'], 54.20, -0.27),
    ]);

    $this->artisan('ingest:bathing-waters')->assertSuccessful();
    $this->artisan('ingest:bathing-waters')->assertSuccessful();

    expect(BathingWater::query()->orderBy('eubwid')->pluck('slug')->all())->toBe(['filey', 'filey-02000']);
});
