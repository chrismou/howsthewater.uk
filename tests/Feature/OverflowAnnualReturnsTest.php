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
    $tinternRow = ['waterCompanyName' => 'Dwr Cymru Welsh Water', 'siteNameEA' => 'Tintern Main SPS', 'permitReferenceEA' => 'BB3691CL', 'UID' => null, 'Latitude' => 51.7009153066649, 'Longitude' => -2.68414930857147, 'countedSpills' => 20, 'totalDurationAllSpillsHrs' => 326];

    Http::fake([
        'returns.test/2025/*' => Http::response(['features' => [
            annualReturnRow(),
            annualReturnRow($tinternRow),
            // A second outlet a few metres away: the closer row keeps the monitor, this one stays unmatched.
            annualReturnRow([...$tinternRow, 'siteNameEA' => 'Tintern Main SPS (Emergency)', 'Latitude' => 51.70095, 'Longitude' => -2.68420]),
            // Nothing within 25 metres.
            annualReturnRow(['UID' => null, 'siteNameEA' => 'FAR AWAY CSO', 'permitReferenceEA' => 'YWZZ1/1', 'Latitude' => 52.0, 'Longitude' => -1.0]),
        ]]),
        // 2021 numbers its rows, names fields in another case and places rows at the permit's grid reference, 120 m
        // from the outlet, so it is matched through the permit the 2025 row shares.
        'returns.test/2021/*' => Http::response(['features' => [
            ['attributes' => ['waterCompanyName' => 'Yorkshire Water', 'SiteNameEA' => 'ELLERTON SSO', 'permitReferenceEA' => 'YWUCD2/36', 'UID' => 16582, 'countedSpills' => 92, 'totalDurationAllSpillsHrs' => 700.5, 'edmOperationPercent' => 99.1, 'Latitude' => 53.84950754, 'Longitude' => -0.93122703]],
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

it('replaces a year on each import and keeps a year whose read fails', function () {
    Sleep::fake();
    Log::spy();
    OverflowMonitor::factory()->create(['external_id' => 'YWS02215', 'latitude' => 53.849732, 'longitude' => -0.93284]);
    OverflowAnnualReturn::factory()->create(['year' => 2025, 'uid' => 'OLD00001']);
    OverflowAnnualReturn::factory()->create(['year' => 2021, 'uid' => 'KEPT0001']);
    Http::fake([
        'returns.test/2025/*' => Http::response(['features' => [annualReturnRow()]]),
        'returns.test/2021/*' => Http::response('Service unavailable', 503),
    ]);

    $this->artisan('ingest:overflow-annual-returns')->assertFailed();

    expect(OverflowAnnualReturn::query()->orderBy('year')->pluck('uid')->all())->toBe(['KEPT0001', 'YWS02215']);
    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'annual returns for 2021 failed'));
});

it('imports on deploy until every year has been read under the current matching rules, then skips', function () {
    OverflowAnnualReturn::factory()->create(['year' => 2025, 'uid' => 'OLD00001']);
    Http::fake(['returns.test/*' => Http::response(['features' => [annualReturnRow()]])]);

    // Returns stored by an older release, with no record of how they were matched, are imported again.
    $this->artisan('ingest:overflow-annual-returns --if-outdated')->expectsOutputToContain('Imported 2 years')->assertSuccessful();
    $this->artisan('ingest:overflow-annual-returns --if-outdated')->expectsOutputToContain('already imported and matched')->assertSuccessful();

    Http::assertSentCount(2);
});

it('matches by permit across years only where the permit names one outlet', function () {
    $first = OverflowMonitor::factory()->create(['external_id' => 'YWS00001', 'latitude' => 54.0, 'longitude' => -1.0]);
    $second = OverflowMonitor::factory()->create(['external_id' => 'YWS00002', 'latitude' => 54.1, 'longitude' => -1.1]);
    $third = OverflowMonitor::factory()->create(['external_id' => 'YWS00003', 'latitude' => 54.2, 'longitude' => -1.2]);
    $fourth = OverflowMonitor::factory()->create(['external_id' => 'YWS00004', 'latitude' => 54.3, 'longitude' => -1.3]);
    config(['services.overflow_annual_returns' => [
        2025 => 'https://returns.test/2025/FeatureServer/0',
        2024 => 'https://returns.test/2024/FeatureServer/0',
        2021 => 'https://returns.test/2021/FeatureServer/0',
    ]]);
    // In 2021 every row sits far from its outlet, so only the permit can match it.
    $far = ['UID' => 16000, 'Latitude' => 50.0, 'Longitude' => 0.0];

    Http::fake([
        // Two overflows at one works share a permit and a name.
        'returns.test/2025/*' => Http::response(['features' => [
            annualReturnRow(['UID' => 'YWS00001', 'permitReferenceEA' => 'SHARED/1', 'siteNameEA' => 'WORKS CSO', 'Latitude' => 54.0, 'Longitude' => -1.0]),
            annualReturnRow(['UID' => 'YWS00002', 'permitReferenceEA' => 'SHARED/1', 'siteNameEA' => 'WORKS CSO', 'Latitude' => 54.1, 'Longitude' => -1.1]),
            annualReturnRow(['UID' => 'YWS00003', 'permitReferenceEA' => 'MOVED/1', 'siteNameEA' => 'MOVED CSO', 'Latitude' => 54.2, 'Longitude' => -1.2]),
        ]]),
        // The same permit and name matched another overflow a year earlier, so neither can be trusted for 2021.
        'returns.test/2024/*' => Http::response(['features' => [
            annualReturnRow(['UID' => 'YWS00004', 'permitReferenceEA' => 'MOVED/1', 'siteNameEA' => 'MOVED CSO', 'Latitude' => 54.3, 'Longitude' => -1.3]),
        ]]),
        'returns.test/2021/*' => Http::response(['features' => [
            annualReturnRow([...$far, 'permitReferenceEA' => 'SHARED/1', 'siteNameEA' => 'WORKS CSO', 'countedSpills' => 111]),
            annualReturnRow([...$far, 'permitReferenceEA' => 'SHARED/1', 'siteNameEA' => 'WORKS CSO', 'countedSpills' => 222]),
            annualReturnRow([...$far, 'permitReferenceEA' => 'MOVED/1', 'siteNameEA' => 'MOVED CSO', 'countedSpills' => 333]),
        ]]),
    ]);

    $this->artisan('ingest:overflow-annual-returns')->assertSuccessful();

    expect(OverflowAnnualReturn::query()->where('year', 2021)->whereNotNull('overflow_monitor_id')->exists())->toBeFalse()
        ->and($first->annualReturns()->pluck('year')->all())->toBe([2025])
        ->and($second->annualReturns()->pluck('year')->all())->toBe([2025])
        ->and($third->annualReturns()->pluck('year')->all())->toBe([2025])
        ->and($fourth->annualReturns()->pluck('year')->all())->toBe([2024]);
});
