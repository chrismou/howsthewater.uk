<?php

use App\Actions\Ingest\SyncSepaBathingWaterSamples;
use App\Enums\Agency;
use App\Enums\BathingWaterClassification;
use App\Models\BathingWater;
use App\Models\Region;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A SEPA results page with the given sample rows ([date, E. coli,
 * enterococci]) and classification rows ([year, classification]).
 *
 * @param  list<array{0: string, 1: string, 2: string}>  $samples
 * @param  list<array{0: string, 1: string}>  $classifications
 */
function sepaResultsPage(array $samples, array $classifications = []): string
{
    $sampleRows = collect($samples)->map(fn (array $row) => '<tr><td>'.e($row[0]).'</td><td>'.e($row[1]).'</td><td>'.e($row[2]).'</td></tr>')->join('');
    $classificationRows = collect($classifications)->map(fn (array $row) => "<tr><td>{$row[0]}</td><td>{$row[1]}</td></tr>")->join('');

    return <<<HTML
        <html><body>
        <table id="tblSampleResults"><thead><tr><th>Date</th><th>Escherichia coli (cfu/100ml)</th><th>Intestinal enterococci (cfu/100ml)</th></tr></thead>
        <tbody>{$sampleRows}</tbody></table>
        <table id="tblHistoricClassifications"><thead><tr><th>Year</th><th>Classification</th></tr></thead>
        <tbody>{$classificationRows}</tbody></table>
        </body></html>
        HTML;
}

function sepaSite(array $attributes = []): BathingWater
{
    $location = fake()->unique()->numerify('#####');

    return BathingWater::factory()->create([
        'agency' => Agency::Sepa,
        'eubwid' => 'UKS76'.$location,
        'sampling_point' => $location,
        'profile_url' => "https://bathingwaters.sepa.org.uk/locations-and-results/results?location={$location}",
        ...$attributes,
    ]);
}

beforeEach(function () {
    config(['services.sepa.bathing_samples.enabled' => true, 'services.sepa.bathing_samples.interval_days' => 7]);
    $this->travelTo(Carbon::parse('2026-07-16 12:00', 'Europe/London'));
});

it('stores the samples and classifications from a results page', function () {
    $region = Region::factory()->create(['slug' => 'scotland']);
    $site = sepaSite(['region_id' => $region->id, 'name' => 'Portobello (Central)', 'slug' => 'portobello-central']);
    Http::fake(['bathingwaters.sepa.org.uk/*' => Http::response(sepaResultsPage(
        [['18 May 2026', '500', '<10'], ['01 June 2026', '1,900', '130'], ['14 July 2026', '>24000', '20']],
        [['2025', 'Sufficient'], ['2024', 'Good']],
    ))]);

    $this->artisan('ingest:sepa-bathing-samples')
        ->expectsOutputToContain('1 SEPA results pages read, 3 new samples.')
        ->assertSuccessful();

    $samples = $site->samples()->get();
    $site->refresh();

    expect($samples)->toHaveCount(3)
        ->and($samples[0]->sampled_at->toIso8601String())->toBe('2026-07-13T23:00:00+00:00')
        ->and($samples[0]->e_coli)->toBe(24000)
        ->and($samples[0]->e_coli_qualifier)->toBe('>')
        ->and($samples[1]->e_coli)->toBe(1900)
        ->and($samples[2]->enterococci)->toBe(10)
        ->and($samples[2]->enterococci_qualifier)->toBe('<')
        ->and($site->classifications()->pluck('classification', 'year')->all())->toBe([2025 => BathingWaterClassification::Sufficient, 2024 => BathingWaterClassification::Good])
        ->and($site->samples_checked_at?->equalTo(now()))->toBeTrue();

    $this->get(route('beaches.show', [$region, $site]))
        ->assertOk()
        ->assertSee('24,000')
        ->assertSee('SEPA results page')
        ->assertDontSee('SEPA publishes this beach');
});

it('reads nothing while switched off or out of season', function () {
    sepaSite();
    Http::fake();

    config(['services.sepa.bathing_samples.enabled' => false]);
    $this->artisan('ingest:sepa-bathing-samples')->expectsOutputToContain('skipped: switched off')->assertSuccessful();

    config(['services.sepa.bathing_samples.enabled' => true]);
    $this->travelTo(Carbon::parse('2026-10-16 12:00', 'Europe/London'));
    $this->artisan('ingest:sepa-bathing-samples')->expectsOutputToContain('skipped: out of season')->assertSuccessful();

    Http::assertNothingSent();
});

it('reads one site per turn, spacing turns so every site is read once per interval', function () {
    // Four sites over seven days: a turn every 42 hours.
    $read = sepaSite(['samples_checked_at' => now()->subDays(3)]);
    $longestAgo = sepaSite(['samples_checked_at' => now()->subDays(6)]);
    $never = sepaSite();
    $failedLately = sepaSite(['samples_retry_at' => now()->addHour()]);
    Http::fake(['bathingwaters.sepa.org.uk/*' => Http::response(sepaResultsPage([['14 July 2026', '10', '10']]))]);
    $sync = app(SyncSepaBathingWaterSamples::class);

    expect($sync->minutesBetweenReads())->toBe(42.0 * 60);

    // Never read comes first, but a site whose read failed waits for its retry time.
    expect($sync->handle()['checked'])->toBe(1)
        ->and($never->fresh()->samples_checked_at?->equalTo(now()))->toBeTrue();

    expect($sync->handle()['skipped'])->toBe('not due yet');

    // Once its retry time has passed, the failed site is next, as it has never been read.
    $this->travel(42)->hours();
    expect($sync->handle()['checked'])->toBe(1)
        ->and($failedLately->fresh()->samples_checked_at?->equalTo(now()))->toBeTrue()
        ->and($failedLately->fresh()->samples_retry_at)->toBeNull();

    $this->travel(42)->hours();
    expect($sync->handle()['checked'])->toBe(1)
        ->and($longestAgo->fresh()->samples_checked_at?->equalTo(now()))->toBeTrue()
        ->and($read->fresh()->samples_checked_at?->lessThan(now()->subDays(6)))->toBeTrue();

    Http::assertSentCount(3);
});

it('reads no more often than one pass a day, however low the interval is set', function () {
    config(['services.sepa.bathing_samples.interval_days' => 0.1]);

    expect(app(SyncSepaBathingWaterSamples::class)->intervalDays())->toBe(1.0);
});

it('pauses every read for a day when SEPA refuses a request, and logs it', function () {
    Log::spy();
    $first = sepaSite();
    sepaSite();
    Http::fake(['bathingwaters.sepa.org.uk/*' => Http::response('Forbidden', 403)]);

    $this->artisan('ingest:sepa-bathing-samples')
        ->expectsOutputToContain('SEPA results page request failed')
        ->assertFailed();

    Http::assertSentCount(1);
    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'HTTP 403'));
    expect($first->fresh()->samples_retry_at?->equalTo(now()->addDay()))->toBeTrue();

    $this->travel(23)->hours();
    $this->artisan('ingest:sepa-bathing-samples')->expectsOutputToContain('skipped: paused')->assertSuccessful();
    Http::assertSentCount(1);
});

it('treats a page without its sample table as a failure, not as no samples', function () {
    Log::spy();
    $site = sepaSite();
    Http::fake(['bathingwaters.sepa.org.uk/*' => Http::response('<html><body><p>We are updating this page.</p></body></html>')]);

    $this->artisan('ingest:sepa-bathing-samples')->assertFailed();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'no sample table'));
    expect($site->fresh()->samples_checked_at)->toBeNull();

    // Only an hour's pause, since one broken page should not hold up every site for long.
    $this->travel(61)->minutes();
    $this->artisan('ingest:sepa-bathing-samples')->expectsOutputToContain('skipped: no site to read')->assertSuccessful();
    Http::assertSentCount(1);
});
