<?php

use App\Actions\Ingest\SyncGaugeReadings;
use App\Models\GaugeStation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

it('reads every Environment Agency gauge from one latest call, catching up only gauges with a gap, a few per run', function () {
    $current = GaugeStation::factory()->create(['measure_id' => 'CURRENT-level-stage-i-15_min-m']);
    $current->readings()->create(['measured_at' => now()->subMinutes(30), 'value' => 0.4]);
    $new = GaugeStation::factory()->count(25)->create();
    $silent = GaugeStation::factory()->create(['measure_id' => 'SILENT-level-stage-i-15_min-m']);

    Http::fake([
        'environment.data.gov.uk/flood-monitoring/data/readings*' => Http::response(environmentAgencyLatestReadings(
            $new->pluck('measure_id')->push($current->measure_id)->mapWithKeys(fn (string $measureId) => [$measureId => 0.6])->all(),
        )),
        'environment.data.gov.uk/flood-monitoring/id/measures/*' => Http::response(['items' => [
            ['dateTime' => now()->subHours(2)->toIso8601String(), 'value' => 0.5],
            ['dateTime' => now()->subMinutes(15)->toIso8601String(), 'value' => 0.6],
        ]]),
    ]);

    expect(app(SyncGaugeReadings::class)->handle())->toBe(['stations' => 27, 'readings' => 1 + 20 * 2 + 5]);

    expect($current->readings()->count())->toBe(2)
        ->and($current->readings()->first()->value)->toBe(0.6)
        ->and($new->filter(fn (GaugeStation $gauge) => $gauge->readings()->count() === 2))->toHaveCount(20)
        ->and($silent->readings()->exists())->toBeFalse();

    // One bulk call and 20 catch-ups, instead of one call per gauge.
    Http::assertSentCount(21);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), $current->measure_id) || str_contains($request->url(), 'SILENT'));
});

it('keeps the latest readings when a catch-up is refused, and stops the other catch-ups', function () {
    Sleep::fake();
    Log::spy();
    $gauges = GaugeStation::factory()->count(3)->create();

    Http::fake([
        'environment.data.gov.uk/flood-monitoring/data/readings*' => Http::response(environmentAgencyLatestReadings($gauges->pluck('measure_id')->mapWithKeys(fn (string $measureId) => [$measureId => 0.6])->all())),
        'environment.data.gov.uk/flood-monitoring/id/measures/*' => Http::response('<html>403 Forbidden</html>', 403),
    ]);

    expect(app(SyncGaugeReadings::class)->handle()['readings'])->toBe(3);

    expect(Http::recorded(fn (Request $request) => str_contains($request->url(), 'id/measures')))->toHaveCount(2);
    Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message) => str_contains($message, 'catch-up failed'));
});
