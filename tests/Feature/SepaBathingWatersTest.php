<?php

use App\Actions\Ingest\SyncBathingWaterHistory;
use App\Actions\Ingest\SyncBathingWaters;
use App\Enums\Agency;
use App\Enums\BathingWaterClassification;
use App\Enums\BathingWaterKind;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\Region;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fakes SEPA's bathing water map layers: 1 holds points, 2 polygons.
 */
function fakeSepaBathingWaters(): void
{
    Http::fake([
        'map.sepa.org.uk/*/MapServer/1/query*' => Http::response(['features' => [
            ['attributes' => ['description' => 'Portobello (Central)', 'year' => 2025, 'class_description' => 'Sufficient'], 'geometry' => ['x' => -3.1018, 'y' => 55.9527]],
            ['attributes' => ['description' => 'Luss Bay', 'year' => 2025, 'class_description' => 'Good'], 'geometry' => ['x' => -4.638, 'y' => 56.1034]],
        ]]),
        'map.sepa.org.uk/*/MapServer/2/query*' => Http::response(['features' => [
            ['attributes' => ['bw_id' => 'UKS7616044', 'description' => 'Portobello (Central)', 'class_description' => 'Sufficient', 'bw_url' => 'https://bathingwaters.sepa.org.uk/locations-and-results/results?location=4593'], 'geometry' => ['rings' => []]],
            ['attributes' => ['bw_id' => 'UKS7616033', 'description' => 'Luss Bay', 'class_description' => 'Good', 'bw_url' => 'https://bathingwaters.sepa.org.uk/locations-and-results/results?location=125079'], 'geometry' => ['rings' => []]],
            ['attributes' => ['bw_id' => 'UKS7616097', 'description' => 'Ballachulish Peninsula', 'class_description' => 'Unclassified', 'bw_url' => 'https://bathingwaters.sepa.org.uk/locations-and-results/results?location=603688'], 'geometry' => ['rings' => [[[-5.20, 56.68], [-5.18, 56.68], [-5.18, 56.70], [-5.20, 56.70]]]]],
        ]]),
        'environment.data.gov.uk/*' => Http::response(['result' => ['items' => []]]),
    ]);
}

function scotland(): Region
{
    return Region::factory()->create(['name' => 'Scotland', 'slug' => 'scotland', 'bathing_water_districts' => ['City of Edinburgh', 'Argyll and Bute', 'Highland', 'Scotland']]);
}

it('places SEPA beaches in Scotland by council area and inland sites on their loch', function () {
    $scotland = scotland();
    $lomond = Lake::factory()->create(['region_id' => $scotland->id, 'name' => 'Loch Lomond', 'slug' => 'loch-lomond']);
    fakeSepaBathingWaters();

    expect(app(SyncBathingWaters::class)->handleSepa())->toBe(['sites' => 3, 'samples' => 0]);

    $portobello = BathingWater::query()->where('eubwid', 'UKS7616044')->sole();
    $luss = BathingWater::query()->where('eubwid', 'UKS7616033')->sole();
    $ballachulish = BathingWater::query()->where('eubwid', 'UKS7616097')->sole();

    expect($portobello->agency)->toBe(Agency::Sepa)
        ->and($portobello->region_id)->toBe($scotland->id)
        ->and($portobello->kind)->toBe(BathingWaterKind::Coastal)
        ->and($portobello->district)->toBe('City of Edinburgh')
        ->and($portobello->classification)->toBe(BathingWaterClassification::Sufficient)
        ->and($portobello->classification_year)->toBe(2025)
        ->and($portobello->sampling_point)->toBe('4593')
        ->and($portobello->profile_url)->toBe('https://bathingwaters.sepa.org.uk/locations-and-results/results?location=4593')
        ->and($portobello->season_starts_on->format('m-d'))->toBe('06-01')
        ->and($luss->kind)->toBe(BathingWaterKind::Lake)
        ->and($luss->lake_id)->toBe($lomond->id)
        ->and($ballachulish->classification)->toBeNull()
        ->and($ballachulish->latitude)->toBe(56.69)
        ->and($ballachulish->longitude)->toBe(-5.19);
});

it('never asks the Environment Agency for a SEPA site history', function () {
    scotland();
    fakeSepaBathingWaters();
    app(SyncBathingWaters::class)->handleSepa();

    expect(app(SyncBathingWaterHistory::class)->handle())->toBe(['sites' => 0, 'samples' => 0, 'failed' => []]);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'bathing-water-quality'));
});

it('still syncs English bathing waters when SEPA fails, and logs it', function () {
    Log::spy();
    Http::fake([
        'map.sepa.org.uk/*' => Http::response(['error' => ['code' => 500, 'message' => 'Service unavailable']]),
        'environment.data.gov.uk/*' => Http::response(['result' => ['items' => []]]),
    ]);

    $this->artisan('ingest:bathing-waters --quick')
        ->expectsOutputToContain('0 bathing waters updated')
        ->expectsOutputToContain('SEPA bathing waters request failed')
        ->assertFailed();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'Service unavailable'));
});

it('links a Scottish beach to its SEPA results page in place of samples', function () {
    $scotland = scotland();
    fakeSepaBathingWaters();
    app(SyncBathingWaters::class)->handleSepa();

    $this->get(route('beaches.show', [$scotland, 'portobello-central']))
        ->assertOk()
        ->assertSee('SEPA results page')
        ->assertSee('https://bathingwaters.sepa.org.uk/locations-and-results/results?location=4593', false)
        ->assertDontSee('Environment Agency profile');
});
