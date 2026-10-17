<?php

use App\Models\OverflowAnnualReturn;
use App\Models\OverflowMonitor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * One row of a Rivers Trust annual return layer.
 *
 * @param  array<string, mixed>  $overrides
 * @return array{attributes: array<string, mixed>}
 */
function annualReturnRow(array $overrides = []): array
{
    return ['attributes' => array_merge([
        'waterCompanyName' => 'Yorkshire Water',
        'siteNameEA' => 'ELLERTON CSO',
        'siteNameWASC' => 'ELLERTON/CSO',
        'permitReferenceEA' => 'YWUCD2/36',
        'countedSpills' => 52,
        'totalDurationAllSpillsHrs' => 330,
        'edmOperationPercent' => 95.78,
        'recievingWaterName' => 'DRAIN TO RIVER DERWENT',
        'UID' => 'YWS02215',
        'Latitude' => 53.8497318772774,
        'Longitude' => -0.932839517213122,
    ], $overrides)];
}

beforeEach(function () {
    config(['services.overflow_annual_returns' => [
        2025 => 'https://returns.test/2025/FeatureServer/0',
        2021 => 'https://returns.test/2021/FeatureServer/11',
    ]]);
});

it('imports each year and matches rows to monitors by id, or by position where the id is missing or of another kind', function () {
    $ellerton = OverflowMonitor::factory()->create(['external_id' => 'YWS02215', 'latitude' => 53.849732, 'longitude' => -0.93284]);
    $tintern = OverflowMonitor::factory()->create(['external_id' => 'DCWW710', 'name' => 'Tintern Main Road Storm Overflow', 'company' => 'Dŵr Cymru Welsh Water', 'latitude' => 51.70091853891418, 'longitude' => -2.6841534381369905]);
    $nearby = OverflowMonitor::factory()->create(['external_id' => 'DCWW1380', 'latitude' => 51.70067005642596, 'longitude' => -2.6742664082239025]);
    $tinternRow = ['waterCompanyName' => 'Dwr Cymru Welsh Water', 'siteNameEA' => 'Tintern Main SPS', 'UID' => null, 'Latitude' => 51.7009153066649, 'Longitude' => -2.68414930857147, 'countedSpills' => 20, 'totalDurationAllSpillsHrs' => 326];

    Http::fake([
        'returns.test/2025/*' => Http::response(['features' => [
            annualReturnRow(),
            annualReturnRow($tinternRow),
            // A second outlet a few metres away: the closer row keeps the monitor, this one stays unmatched.
            annualReturnRow([...$tinternRow, 'siteNameEA' => 'Tintern Main SPS (Emergency)', 'Latitude' => 51.70095, 'Longitude' => -2.68420]),
            // Nothing within 25 metres.
            annualReturnRow(['UID' => null, 'siteNameEA' => 'FAR AWAY CSO', 'Latitude' => 52.0, 'Longitude' => -1.0]),
        ]]),
        // 2021 numbers its rows, and names fields in another case.
        'returns.test/2021/*' => Http::response(['features' => [
            ['attributes' => ['waterCompanyName' => 'Yorkshire Water', 'SiteNameEA' => 'ELLERTON SSO', 'UID' => 16582, 'countedSpills' => 92, 'totalDurationAllSpillsHrs' => 700.5, 'edmOperationPercent' => 99.1, 'Latitude' => 53.84973188, 'Longitude' => -0.932839517]],
        ]]),
    ]);

    $this->artisan('ingest:overflow-annual-returns')
        ->expectsOutputToContain('Imported 2 years: 5 rows, 3 matched to monitors.')
        ->assertSuccessful();

    $returns = $ellerton->annualReturns()->get();

    expect($returns->pluck('year')->all())->toBe([2025, 2021])
        ->and($returns[0]->counted_spills)->toBe(52)
        ->and((float) $returns[0]->total_hours)->toBe(330.0)
        ->and((float) $returns[0]->monitor_uptime_percent)->toBe(95.78)
        ->and($returns[0]->permit_reference)->toBe('YWUCD2/36')
        ->and($returns[1]->uid)->toBeNull()
        ->and($returns[1]->counted_spills)->toBe(92)
        ->and($tintern->annualReturns()->sole()->site_name)->toBe('Tintern Main SPS')
        ->and($nearby->annualReturns()->exists())->toBeFalse()
        ->and(OverflowAnnualReturn::query()->whereNull('overflow_monitor_id')->pluck('site_name')->sort()->values()->all())->toBe(['FAR AWAY CSO', 'Tintern Main SPS (Emergency)'])
        // Named from the latest return, tidied; a feed's own name still comes first.
        ->and($ellerton->fresh()->displayName())->toBe('Ellerton CSO')
        ->and($tintern->fresh()->return_name)->toBe('Tintern Main SPS')
        ->and($tintern->fresh()->displayName())->toBe('Tintern Main Road Storm Overflow');
});

it('replaces a year on each import, keeps a year whose read fails, and skips on deploy once returns are stored', function () {
    Sleep::fake();
    Log::spy();
    OverflowMonitor::factory()->create(['external_id' => 'YWS02215', 'latitude' => 53.849732, 'longitude' => -0.93284]);
    OverflowAnnualReturn::factory()->create(['year' => 2025, 'uid' => 'OLD00001']);
    OverflowAnnualReturn::factory()->create(['year' => 2021, 'uid' => 'KEPT0001']);
    Http::fake([
        'returns.test/2025/*' => Http::response(['features' => [annualReturnRow()]]),
        'returns.test/2021/*' => Http::response('Service unavailable', 503),
    ]);

    $this->artisan('ingest:overflow-annual-returns --if-empty')->expectsOutputToContain('already imported')->assertSuccessful();
    Http::assertNothingSent();

    $this->artisan('ingest:overflow-annual-returns')->assertFailed();

    expect(OverflowAnnualReturn::query()->orderBy('year')->pluck('uid')->all())->toBe(['KEPT0001', 'YWS02215']);
    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'annual returns for 2021 failed'));
});
