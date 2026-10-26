<?php

use App\Actions\Ingest\SyncFloodAreas;
use App\Data\FloodWarningSummary;
use App\Enums\Agency;
use App\Enums\FloodSeverity;
use App\Services\Conditions\ChangedRegions;
use App\Models\FloodArea;
use App\Models\FloodWarning;
use App\Models\FloodWarningEvent;
use App\Models\River;
use App\Models\Spot;
use App\Services\Sepa\FloodWarningsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A row from the Floodline map's flood alert areas layer.
 *
 * @return array<string, mixed>
 */
function sepaAlertRow(string $name, int $id, ?string $severity, ?string $startTime = '2026-07-01T12:27:25.113', ?string $updatedTime = '2026-07-01T17:11:17.767'): array
{
    return [
        'name' => $name,
        'id' => (float) $id,
        'severity' => $severity,
        'message' => $severity === null ? null : "A Flood Alert has been issued for {$name}.",
        'startTime' => $severity === null ? null : $startTime,
        'endTime' => null,
        'updatedTime' => $severity === null ? null : $updatedTime,
    ];
}

/**
 * A row from the Floodline map's flood warning areas layer.
 *
 * @return array<string, mixed>
 */
function sepaWarningRow(string $name, int $mapId, string $scheme, ?string $severity, string $source = 'Fluvial', ?string $startTime = '2026-07-01T09:11:39.197', ?string $updatedTime = '2026-07-01T17:11:17.767'): array
{
    return [
        'fwa_name' => $name,
        'map_id' => $mapId,
        'scheme' => $scheme,
        'source_of_flood' => $source,
        'local_authority' => 'Perth and Kinross Council',
        'severity' => $severity,
        'message' => $severity === null ? null : ($severity === 'NLIF' ? "The Flood Warning for {$name} is no longer in force." : "A Flood Warning has been issued for {$name}."),
        'startTime' => $severity === null ? null : $startTime,
        'endTime' => $severity === 'NLIF' ? '2026-07-01T21:11:39.197' : null,
        'updatedTime' => $severity === null ? null : $updatedTime,
    ];
}

/**
 * An ArcGIS query response for a layer, listing the fields the layer has.
 *
 * @param  array<int, string>  $fields
 * @param  array<int, array<string, mixed>>  $rows
 * @return array<string, mixed>
 */
function sepaLayerResponse(array $fields, array $rows): array
{
    return [
        'objectIdFieldName' => 'ObjectId',
        'fields' => collect($fields)->map(fn (string $name) => ['name' => $name, 'type' => 'esriFieldTypeString'])->all(),
        'features' => collect($rows)->map(fn (array $row) => ['attributes' => $row])->all(),
        'exceededTransferLimit' => false,
    ];
}

/**
 * An Environment Agency flood warning in force, so the command's England step keeps the area's stored warning.
 *
 * @return array<string, mixed>
 */
function environmentAgencyWarningInForce(string $code): array
{
    return [
        'floodAreaID' => $code,
        'floodArea' => ['notation' => $code],
        'severityLevel' => 2,
        'message' => 'Flooding is expected.',
        'timeRaised' => '2026-07-01T08:47:26',
        'timeMessageChanged' => '2026-07-01T09:15:00',
    ];
}

/**
 * A layer's own description, as ArcGIS gives it, with the time SEPA last wrote to it.
 *
 * @return array<string, mixed>
 */
function sepaLayerDescription(?Carbon $lastEditedAt): array
{
    return [
        'name' => 'Flood_Warning_Areas_(public)_view',
        'type' => 'Feature Layer',
        'maxRecordCount' => 2000,
        'editingInfo' => $lastEditedAt === null ? [] : ['lastEditDate' => $lastEditedAt->getTimestampMs()],
    ];
}

/**
 * Fakes the two Floodline layers and the Environment Agency, answering every query from the returned holder, which a
 * test can change between polls (a second Http::fake() would not replace the first). The layers' last edit defaults
 * to just after the rows' default updatedTime, 16:11 UTC on 1 July 2026.
 *
 * @param  array<int, array<string, mixed>>  $alerts
 * @param  array<int, array<string, mixed>>  $warnings
 * @param  array<int, string>|null  $alertFields
 * @param  array<int, string>|null  $warningFields
 * @param  array<int, array<string, mixed>>  $environmentAgencyFloods
 * @return ArrayObject<string, mixed>
 */
function fakeSepaFloodLayers(array $alerts, array $warnings, ?array $alertFields = null, ?array $warningFields = null, array $environmentAgencyFloods = [], ?Carbon $lastEditedAt = null): ArrayObject
{
    $layers = new ArrayObject([
        'alerts' => $alerts,
        'warnings' => $warnings,
        'alertFields' => $alertFields ?? FloodWarningsClient::ALERT_FIELDS,
        'warningFields' => $warningFields ?? FloodWarningsClient::WARNING_FIELDS,
        'lastEditedAt' => $lastEditedAt ?? Carbon::parse('2026-07-01T16:16:04Z'),
    ]);

    Http::fake([
        'services-eu1.arcgis.com/*Flood_Alert_Areas*/query*' => fn () => Http::response(sepaLayerResponse($layers['alertFields'], $layers['alerts'])),
        'services-eu1.arcgis.com/*Flood_Warning_Areas*/query*' => fn () => Http::response(sepaLayerResponse($layers['warningFields'], $layers['warnings'])),
        'services-eu1.arcgis.com/*' => fn () => Http::response(sepaLayerDescription($layers['lastEditedAt'])),
        'environment.data.gov.uk/flood-monitoring/id/floods*' => Http::response(['items' => $environmentAgencyFloods]),
        'environment.data.gov.uk/*' => Http::response(['items' => []]),
    ]);

    return $layers;
}

beforeEach(function () {
    config(['services.sepa.flood_warnings.enabled' => true]);
});

it('reads the alerts and warnings in force from the layers behind the Floodline map', function () {
    fakeSepaFloodLayers(
        [sepaAlertRow('Tayside', 10000, 'Alert')],
        [
            sepaWarningRow('Innerpeffray to Bridge of Earn', 1022, 'Earn', 'Warning'),
            sepaWarningRow('Glen Lyon', 1034, 'Tay', 'NLIF'),
            sepaWarningRow('Stonehaven', 1286, 'Aberdeenshire and Angus', 'Severe Flood Warning', 'Coastal/Fluvial'),
        ],
    );

    $feed = app(FloodWarningsClient::class)->floodWarnings();

    expect($feed->pluck('floodAreaCode')->all())->toBe(['SEPA-A-10000', 'SEPA-W-1022', 'SEPA-W-1034', 'SEPA-W-1286'])
        ->and($feed->pluck('severity')->all())->toBe([FloodSeverity::FloodAlert, FloodSeverity::FloodWarning, null, FloodSeverity::SevereFloodWarning])
        ->and($feed[2]->isInForce())->toBeFalse()
        ->and($feed[0]->areaLabel)->toBe('Tayside')
        ->and($feed[0]->message)->toBe('A Flood Alert has been issued for Tayside.')
        ->and($feed[0]->raisedAt->toIso8601String())->toBe('2026-07-01T11:27:25+00:00')
        ->and($feed[0]->messageChangedAt->toIso8601String())->toBe('2026-07-01T11:27:25+00:00')
        ->and($feed[0]->publishedAt->toIso8601String())->toBe('2026-07-01T16:11:17+00:00')
        ->and($feed[1]->riverOrSea)->toBe('Earn')
        ->and($feed[1]->county)->toBe('Perth and Kinross Council')
        ->and($feed[2]->severityChangedAt->toIso8601String())->toBe('2026-07-01T08:11:39+00:00');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'Flood_Warning_Areas')
        && $request['where'] === 'severity IS NOT NULL'
        && $request['outFields'] === implode(',', FloodWarningsClient::WARNING_FIELDS)
        && $request['returnGeometry'] === 'false');
});

it('stops the read when a layer answers with an error body', function () {
    Http::fake([
        'services-eu1.arcgis.com/*Flood_Alert_Areas*' => Http::response(sepaLayerResponse(FloodWarningsClient::ALERT_FIELDS, [])),
        'services-eu1.arcgis.com/*Flood_Warning_Areas*' => Http::response([
            'error' => ['code' => 400, 'message' => 'Cannot perform query. Invalid query parameters.', 'details' => ["'outFields' parameter is invalid"]],
        ]),
    ]);

    expect(fn () => app(FloodWarningsClient::class)->floodWarnings())
        ->toThrow(RuntimeException::class, "returned error 400: Cannot perform query. Invalid query parameters. 'outFields' parameter is invalid");
});

it('stops the read when a layer no longer lists a field it relies on', function () {
    fakeSepaFloodLayers([], [], warningFields: array_values(array_diff(FloodWarningsClient::WARNING_FIELDS, ['map_id', 'severity'])));

    expect(fn () => app(FloodWarningsClient::class)->floodWarnings())
        ->toThrow(RuntimeException::class, 'no longer has the fields map_id, severity');
});

it('accepts a layer that lists no fields when nothing matched', function () {
    Http::fake([
        'services-eu1.arcgis.com/*Flood_Alert_Areas*' => Http::response(['objectIdFieldName' => 'ObjectId', 'features' => []]),
        'services-eu1.arcgis.com/*Flood_Warning_Areas*' => Http::response(['objectIdFieldName' => 'ObjectId', 'features' => []]),
    ]);

    expect(app(FloodWarningsClient::class)->floodWarnings())->toBeEmpty();
});

it('stops the read when a row lacks a field it relies on', function () {
    $renamed = sepaAlertRow('Tayside', 10000, 'Alert');
    $renamed['status'] = $renamed['severity'];
    unset($renamed['severity']);

    Http::fake([
        'services-eu1.arcgis.com/*Flood_Alert_Areas*' => Http::response(['objectIdFieldName' => 'ObjectId', 'features' => [['attributes' => $renamed]]]),
    ]);

    expect(fn () => app(FloodWarningsClient::class)->floodWarnings())
        ->toThrow(RuntimeException::class, 'returned a row without the fields severity: its schema has changed');
});

it('does not retry a rejected request, and refuses a layer that is not served over https', function () {
    Http::fake(['services-eu1.arcgis.com/*' => Http::response('Forbidden', 403)]);

    expect(fn () => app(FloodWarningsClient::class)->floodWarnings())->toThrow(\Illuminate\Http\Client\RequestException::class);
    Http::assertSentCount(1);

    config(['services.sepa.flood_warnings.alert_areas_url' => 'http://services-eu1.arcgis.com/insecure/FeatureServer/0']);

    expect(fn () => app(FloodWarningsClient::class)->floodWarnings())->toThrow(RuntimeException::class, 'is not an https URL');
});

it('searches only places inside Scotland', function () {
    $client = app(FloodWarningsClient::class);

    expect($client->covers(56.397, -3.437))->toBeTrue()  // Perth
        ->and($client->covers(55.598, -2.434))->toBeTrue()  // Kelso, on the Tweed
        ->and($client->covers(55.651, -2.246))->toBeTrue()  // Coldstream, on the border
        ->and($client->covers(54.835, -4.049))->toBeTrue()  // Kirkcudbright
        ->and($client->covers(60.154, -1.149))->toBeTrue()  // Lerwick
        ->and($client->covers(55.771, -2.007))->toBeFalse()  // Berwick-upon-Tweed
        ->and($client->covers(55.651, -2.120))->toBeFalse()  // Etal, on the Till
        ->and($client->covers(54.971, -2.101))->toBeFalse()  // Hexham, on the Tyne
        ->and($client->covers(55.233, -2.500))->toBeFalse()  // Kielder, on the North Tyne
        ->and($client->covers(54.895, -2.936))->toBeFalse()  // Carlisle, on the Eden
        ->and($client->covers(54.643, -3.544))->toBeFalse(); // Workington, on the Cumbrian Derwent
});

it('stops the read on a severity word it does not know', function () {
    fakeSepaFloodLayers([sepaAlertRow('Tayside', 10000, 'Watch')], []);

    expect(fn () => app(FloodWarningsClient::class)->floodWarnings())
        ->toThrow(RuntimeException::class, 'SEPA flood warning for Tayside has an unrecognised severity "Watch"');
});

it('never reads a numeric or blank severity as lifted', function (mixed $severity, string $shown) {
    fakeSepaFloodLayers([[...sepaAlertRow('Tayside', 10000, 'Alert'), 'severity' => $severity]], []);

    expect(fn () => app(FloodWarningsClient::class)->floodWarnings())
        ->toThrow(RuntimeException::class, "SEPA flood warning for Tayside has an unrecognised severity {$shown}");
})->with([
    'a level number' => [2, '"2"'],
    'a blank' => ['  ', '"  "'],
    'a list' => [['Alert'], 'of type array'],
]);

it('stops the read on a time it cannot read', function () {
    fakeSepaFloodLayers([sepaAlertRow('Tayside', 10000, 'Alert', startTime: '01/07/2026 12:27')], []);
    expect(fn () => app(FloodWarningsClient::class)->floodWarnings())
        ->toThrow(RuntimeException::class, 'SEPA flood warning for Tayside has an unreadable startTime "01/07/2026 12:27"');
});

it('stops the read on a row with a status but no refresh time', function () {
    fakeSepaFloodLayers([sepaAlertRow('Tayside', 10000, 'Alert', updatedTime: null)], []);

    expect(fn () => app(FloodWarningsClient::class)->floodWarnings())
        ->toThrow(RuntimeException::class, 'SEPA flood warning for Tayside has an unreadable updatedTime of type NULL');
});

it('stops the read on an impossible date instead of letting Carbon\'s own exception out', function () {
    fakeSepaFloodLayers([], [sepaWarningRow('Glen Lyon', 1034, 'Tay', 'Warning', startTime: '2026-13-45T10:00:00.000')]);

    expect(fn () => app(FloodWarningsClient::class)->floodWarnings())
        ->toThrow(RuntimeException::class, 'SEPA flood warning for Glen Lyon has an impossible startTime "2026-13-45T10:00:00.000"');
});

it('reads winter times as GMT and summer times as BST', function () {
    fakeSepaFloodLayers([sepaAlertRow('Tayside', 10000, 'Alert', startTime: '2026-01-15T09:30:00.000', updatedTime: '2026-01-15T09:45:00.500')], []);

    $alert = app(FloodWarningsClient::class)->floodWarnings()->sole();

    expect($alert->raisedAt->toIso8601String())->toBe('2026-01-15T09:30:00+00:00')
        ->and($alert->publishedAt->toIso8601String())->toBe('2026-01-15T09:45:00+00:00');
});

it('reads when SEPA last wrote to the layers from their own descriptions', function () {
    fakeSepaFloodLayers([], [], lastEditedAt: Carbon::parse('2026-10-25T18:02:37.123Z'));

    expect(app(FloodWarningsClient::class)->lastRefreshedAt()->toIso8601String())->toBe('2026-10-25T18:02:37+00:00');
});

it('stops when a layer no longer says when it was last written to', function () {
    $layers = fakeSepaFloodLayers([], []);
    $layers['lastEditedAt'] = null;

    expect(fn () => app(FloodWarningsClient::class)->lastRefreshedAt())
        ->toThrow(RuntimeException::class, 'gives no last edit time (editingInfo.lastEditDate)');
});

it('stops the read on an area without an id', function () {
    fakeSepaFloodLayers([], [[...sepaWarningRow('Glen Lyon', 1034, 'Tay', 'Warning'), 'map_id' => null]]);

    expect(fn () => app(FloodWarningsClient::class)->floodWarnings())
        ->toThrow(RuntimeException::class, 'SEPA flood warning area "Glen Lyon" has no whole-number id');
});

it('links Scottish spots to the SEPA areas around them without checking names, and searches no spot elsewhere', function () {
    $tay = River::factory()->create(['name' => 'River Tay', 'aliases' => ['River Tay']]);
    $perth = Spot::factory()->create(['river_id' => $tay->id, 'latitude' => 56.397, 'longitude' => -3.437]);
    $nidd = River::factory()->create(['name' => 'River Nidd', 'aliases' => ['River Nidd']]);
    $english = Spot::factory()->create(['river_id' => $nidd->id, 'latitude' => 53.98, 'longitude' => -1.32]);

    fakeSepaFloodLayers(
        [sepaAlertRow('Tayside', 10000, null)],
        [sepaWarningRow('Basement Properties from North Inch to Friarton Bridge', 1253, 'Almond', null)],
    );

    expect(app(SyncFloodAreas::class)->handle())->toBe(['spots' => 1, 'areas' => 2])
        ->and($perth->floodAreas()->orderBy('code')->pluck('code')->all())->toBe(['SEPA-A-10000', 'SEPA-W-1253'])
        ->and($english->floodAreas()->exists())->toBeFalse();

    $alert = FloodArea::query()->where('code', 'SEPA-A-10000')->sole();
    $warning = FloodArea::query()->where('code', 'SEPA-W-1253')->sole();

    expect($alert->agency)->toBe(Agency::Sepa)
        ->and($alert->label)->toBe('Tayside')
        ->and($alert->description)->toBe("SEPA's regional flood alert area")
        ->and($alert->river_or_sea)->toBeNull()
        ->and($warning->label)->toBe('Basement Properties from North Inch to Friarton Bridge')
        ->and($warning->description)->toBe('SEPA flood warning area for river flooding in Perth and Kinross Council')
        ->and($warning->river_or_sea)->toBe('Almond');

    $searches = Http::recorded(fn (Request $request) => str_contains($request->url(), 'arcgis.com'));

    expect($searches)->toHaveCount(2);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'Flood_Warning_Areas')
        && $request['geometry'] === '-3.437000,56.397000'
        && (int) $request['inSR'] === 4326
        && (int) $request['distance'] === 1000
        && $request['units'] === 'esriSRUnit_Meter');
});

it('records SEPA warnings and their history from the scheduled command, confirmed as of the layers\' last refresh', function () {
    Carbon::setTestNow('2026-07-01 16:20:00');
    $spot = Spot::factory()->create(['latitude' => 56.397, 'longitude' => -3.437]);
    $tayside = FloodArea::factory()->hasAttached($spot)->create(['code' => 'SEPA-A-10000', 'agency' => Agency::Sepa, 'label' => 'Tayside']);
    $english = FloodArea::factory()->hasAttached($spot)->create(['code' => '122WAF933']);
    FloodWarning::factory()->create(['flood_area_id' => $english->id]);
    FloodWarningEvent::factory()->create(['flood_area_id' => $english->id, 'ended_at' => null]);

    $layers = fakeSepaFloodLayers(
        [sepaAlertRow('Tayside', 10000, 'Alert')],
        [
            sepaWarningRow('Innerpeffray to Bridge of Earn', 1022, 'Earn', 'Warning'),
            sepaWarningRow('Glen Lyon', 1034, 'Tay', 'NLIF'),
        ],
        environmentAgencyFloods: [environmentAgencyWarningInForce('122WAF933')],
    );

    $this->artisan('ingest:floods --quick')->assertSuccessful();

    $warning = FloodWarning::query()->where('flood_area_id', $tayside->id)->with('floodArea')->sole();
    $summary = FloodWarningSummary::fromWarning($warning);

    expect($warning->severity_level)->toBe(FloodSeverity::FloodAlert)
        ->and($warning->message)->toBe('A Flood Alert has been issued for Tayside.')
        ->and($warning->checked_at->toIso8601String())->toBe('2026-07-01T16:11:17+00:00')
        ->and($summary->isStale())->toBeFalse()
        ->and($summary->url())->toBe('https://floodline.sepa.org.uk/live-flood-information/')
        ->and($summary->urlLabel())->toBe('Read the latest on Floodline')
        ->and(FloodWarning::query()->where('flood_area_id', $english->id)->exists())->toBeTrue()
        ->and(FloodWarningEvent::query()->where('flood_area_id', $english->id)->whereNull('ended_at')->exists())->toBeTrue();

    $earn = FloodArea::query()->where('code', 'SEPA-W-1022')->sole();

    expect($earn->agency)->toBe(Agency::Sepa)
        ->and($earn->label)->toBe('Innerpeffray to Bridge of Earn')
        ->and($earn->river_or_sea)->toBe('Earn')
        ->and($earn->county)->toBe('Perth and Kinross Council')
        ->and(FloodWarningEvent::query()->whereNull('ended_at')->whereHas('floodArea', fn ($query) => $query->where('agency', Agency::Sepa))->pluck('flood_area_id')->sort()->values()->all())
        ->toBe(collect([$tayside->id, $earn->id])->sort()->values()->all())
        ->and(FloodArea::query()->where('code', 'SEPA-W-1034')->exists())->toBeFalse();

    // SEPA marks the warning lifted at 16:30 UK time and the alert stays, so only the alert's event remains open.
    $layers['alerts'] = [sepaAlertRow('Tayside', 10000, 'Alert', updatedTime: '2026-07-01T17:16:00.000')];
    $layers['warnings'] = [sepaWarningRow('Innerpeffray to Bridge of Earn', 1022, 'Earn', 'NLIF', startTime: '2026-07-01T16:30:00.000', updatedTime: '2026-07-01T17:16:00.000')];
    Carbon::setTestNow('2026-07-01 16:35:00');

    $this->artisan('ingest:floods --quick')->assertSuccessful();

    expect(FloodWarningEvent::query()->where('flood_area_id', $earn->id)->sole()->ended_at->toIso8601String())->toBe('2026-07-01T15:30:00+00:00')
        ->and(FloodWarningEvent::query()->where('flood_area_id', $tayside->id)->whereNull('ended_at')->exists())->toBeTrue()
        ->and(FloodWarning::query()->where('flood_area_id', $tayside->id)->value('checked_at')->toIso8601String())->toBe('2026-07-01T16:16:00+00:00');
});

it('fails the run and reads nothing when SEPA has not refreshed its layers for hours, so stored warnings go stale', function () {
    Carbon::setTestNow('2026-07-02 01:00:00');
    Log::spy();
    $spot = Spot::factory()->create(['latitude' => 56.397, 'longitude' => -3.437]);
    $tayside = FloodArea::factory()->hasAttached($spot)->create(['code' => 'SEPA-A-10000', 'agency' => Agency::Sepa]);
    FloodWarning::factory()->create(['flood_area_id' => $tayside->id, 'severity_level' => FloodSeverity::FloodAlert, 'checked_at' => Carbon::parse('2026-07-01T16:11:17+00:00')]);
    FloodWarningEvent::factory()->create(['flood_area_id' => $tayside->id, 'severity_level' => FloodSeverity::FloodAlert, 'ended_at' => null]);

    // The layer still shows the alert in force, but has not been rewritten since yesterday afternoon.
    fakeSepaFloodLayers([sepaAlertRow('Tayside', 10000, 'Alert')], [sepaWarningRow('Glen Lyon', 1034, 'Tay', 'Warning')]);

    $this->artisan('ingest:floods --quick')->assertFailed();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, "SEPA's flood warning layers were last refreshed"))->once();

    $warning = FloodWarning::query()->where('flood_area_id', $tayside->id)->with('floodArea')->sole();

    expect($warning->checked_at->toIso8601String())->toBe('2026-07-01T16:11:17+00:00')
        ->and(FloodWarningSummary::fromWarning($warning)->isStale())->toBeTrue()
        ->and(FloodArea::query()->where('code', 'SEPA-W-1034')->exists())->toBeFalse()
        ->and(FloodWarningEvent::query()->where('flood_area_id', $tayside->id)->whereNull('ended_at')->exists())->toBeTrue();
});

it('falls back to the layers\' own last edit time when no area has a status', function () {
    Carbon::setTestNow('2026-07-02 01:00:00');
    Log::spy();

    fakeSepaFloodLayers([], []);

    $this->artisan('ingest:floods --quick')->assertFailed();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, "SEPA's flood warning layers were last refreshed 8 hours ago"))->once();
});

it('does not let a ticking service time hide status rows that stopped refreshing', function () {
    Carbon::setTestNow('2026-07-02 01:00:00');
    Log::spy();
    $spot = Spot::factory()->create(['latitude' => 56.397, 'longitude' => -3.437]);
    FloodArea::factory()->hasAttached($spot)->create(['code' => 'SEPA-A-10000', 'agency' => Agency::Sepa]);

    // The layer was written to a minute ago, but its status rows still carry yesterday afternoon's refresh time.
    fakeSepaFloodLayers([sepaAlertRow('Tayside', 10000, 'Alert')], [], lastEditedAt: Carbon::parse('2026-07-02 00:59:00'));

    $this->artisan('ingest:floods --quick')->assertFailed();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, "SEPA's flood warning layers were last refreshed"))->once();

    expect(FloodWarning::query()->exists())->toBeFalse();
});

it('reads a lifted-only feed as long as the layers are fresh', function () {
    Carbon::setTestNow('2026-07-01 16:20:00');
    $spot = Spot::factory()->create(['latitude' => 56.397, 'longitude' => -3.437]);
    $earn = FloodArea::factory()->hasAttached($spot)->create(['code' => 'SEPA-W-1022', 'agency' => Agency::Sepa]);
    FloodWarning::factory()->severity(FloodSeverity::FloodWarning)->create(['flood_area_id' => $earn->id]);
    FloodWarningEvent::factory()->severity(FloodSeverity::FloodWarning)->create(['flood_area_id' => $earn->id, 'ended_at' => null]);

    fakeSepaFloodLayers([], [sepaWarningRow('Innerpeffray to Bridge of Earn', 1022, 'Earn', 'NLIF', startTime: '2026-07-01T16:30:00.000')]);

    $this->artisan('ingest:floods --quick')->assertSuccessful();

    expect(FloodWarning::query()->where('flood_area_id', $earn->id)->exists())->toBeFalse()
        ->and(FloodWarningEvent::query()->where('flood_area_id', $earn->id)->sole()->ended_at->toIso8601String())->toBe('2026-07-01T15:30:00+00:00');
});

it('fails the run and reads nothing when the layers are dated ahead of our clock', function () {
    Carbon::setTestNow('2026-07-01 09:00:00');
    Log::spy();
    $spot = Spot::factory()->create(['latitude' => 56.397, 'longitude' => -3.437]);
    FloodArea::factory()->hasAttached($spot)->create(['code' => 'SEPA-A-10000', 'agency' => Agency::Sepa]);

    // updatedTime 17:11 UK time is 16:11 UTC, seven hours ahead of now.
    fakeSepaFloodLayers([sepaAlertRow('Tayside', 10000, 'Alert')], []);

    $this->artisan('ingest:floods --quick')->assertFailed();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'ahead of our clock'))->once();

    expect(FloodWarning::query()->exists())->toBeFalse();
});

it('still marks regions changed and keeps the Environment Agency\'s warnings when SEPA fails', function (array $layers) {
    Log::spy();
    $this->mock(ChangedRegions::class)->shouldReceive('markAll')->once();
    $spot = Spot::factory()->create();
    $english = FloodArea::factory()->hasAttached($spot)->create(['code' => '122WAF933']);

    Http::fake([
        ...$layers,
        'environment.data.gov.uk/flood-monitoring/id/floods*' => Http::response(['items' => [environmentAgencyWarningInForce('122WAF933')]]),
        'environment.data.gov.uk/*' => Http::response(['items' => []]),
    ]);

    $this->artisan('ingest:floods --quick')->assertFailed();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_starts_with($message, 'SEPA flood warnings failed'))->once();

    expect(FloodWarning::query()->where('flood_area_id', $english->id)->exists())->toBeTrue();
})->with([
    'a server error' => fn () => ['services-eu1.arcgis.com/*' => Http::response('Service unavailable', 503)],
    'a redirect' => fn () => ['services-eu1.arcgis.com/*' => Http::response('', 301, ['Location' => 'https://elsewhere.example/'])],
    'a body that is not JSON' => fn () => ['services-eu1.arcgis.com/*' => Http::response('<html>maintenance</html>', 200, ['Content-Type' => 'text/html'])],
    'a page of rows that overflowed' => fn () => [
        'services-eu1.arcgis.com/*/query*' => Http::response([...sepaLayerResponse(FloodWarningsClient::ALERT_FIELDS, []), 'exceededTransferLimit' => true]),
        'services-eu1.arcgis.com/*' => Http::response(sepaLayerDescription(Carbon::parse('2026-07-01T16:16:04Z'))),
    ],
    'a layer without its features list' => fn () => [
        'services-eu1.arcgis.com/*/query*' => Http::response(['objectIdFieldName' => 'ObjectId']),
        'services-eu1.arcgis.com/*' => Http::response(sepaLayerDescription(Carbon::parse('2026-07-01T16:16:04Z'))),
    ],
]);

it('takes SEPA\'s warnings off the pages and closes their history once switched off', function () {
    config(['services.sepa.flood_warnings.enabled' => false]);
    $spot = Spot::factory()->create(['latitude' => 56.397, 'longitude' => -3.437]);
    $tayside = FloodArea::factory()->hasAttached($spot)->create(['code' => 'SEPA-A-10000', 'agency' => Agency::Sepa]);
    $english = FloodArea::factory()->hasAttached($spot)->create(['code' => '122WAF933']);
    FloodWarning::factory()->create(['flood_area_id' => $tayside->id]);
    FloodWarning::factory()->create(['flood_area_id' => $english->id]);
    FloodWarningEvent::factory()->create(['flood_area_id' => $tayside->id, 'ended_at' => null]);
    FloodWarningEvent::factory()->create(['flood_area_id' => $english->id, 'ended_at' => null]);

    Http::fake([
        'environment.data.gov.uk/flood-monitoring/id/floods*' => Http::response(['items' => [environmentAgencyWarningInForce('122WAF933')]]),
        'environment.data.gov.uk/*' => Http::response(['items' => []]),
    ]);

    $this->artisan('ingest:floods --quick')->assertSuccessful();

    expect(FloodWarning::query()->pluck('flood_area_id')->all())->toBe([$english->id])
        ->and(FloodWarningEvent::query()->where('flood_area_id', $tayside->id)->sole()->ended_at)->not->toBeNull()
        ->and(FloodWarningEvent::query()->where('flood_area_id', $english->id)->whereNull('ended_at')->exists())->toBeTrue();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'arcgis.com'));
});

it('logs an error and fails the run when a layer has changed shape, leaving other agencies\' warnings alone', function () {
    Log::spy();
    $spot = Spot::factory()->create();
    $english = FloodArea::factory()->hasAttached($spot)->create(['code' => '122WAF933']);
    FloodWarning::factory()->create(['flood_area_id' => $english->id]);

    fakeSepaFloodLayers([], [], alertFields: ['name', 'id'], environmentAgencyFloods: [environmentAgencyWarningInForce('122WAF933')]);

    $this->artisan('ingest:floods --quick')->assertFailed();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'SEPA flood warnings failed') && str_contains($message, 'no longer has the fields'))->once();

    expect(FloodWarning::query()->where('flood_area_id', $english->id)->exists())->toBeTrue();
});

it('logs a failed SEPA area search as an error and still syncs the warnings', function () {
    Log::spy();
    $tay = River::factory()->create(['name' => 'River Tay', 'aliases' => ['River Tay']]);
    $perth = Spot::factory()->create(['river_id' => $tay->id, 'latitude' => 56.397, 'longitude' => -3.437]);
    $english = FloodArea::factory()->hasAttached($perth)->create(['code' => '122WAF933']);

    Http::fake([
        'services-eu1.arcgis.com/*' => Http::response(['error' => ['code' => 400, 'message' => 'Cannot perform query. Invalid query parameters.', 'details' => []]]),
        'environment.data.gov.uk/flood-monitoring/id/floods*' => Http::response(['items' => [environmentAgencyWarningInForce('122WAF933')]]),
        'environment.data.gov.uk/*' => Http::response(['items' => []]),
    ]);

    $this->artisan('ingest:floods')->assertFailed();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'Flood area search failed') && str_contains($message, 'returned error 400'))->once();

    expect(FloodWarning::query()->where('flood_area_id', $english->id)->exists())->toBeTrue()
        ->and($perth->fresh()->flood_areas_searched_at)->toBeNull();
});

it('reads nothing from SEPA while switched off', function () {
    config(['services.sepa.flood_warnings.enabled' => false]);
    Spot::factory()->create(['latitude' => 56.397, 'longitude' => -3.437]);

    Http::fake(['environment.data.gov.uk/*' => Http::response(['items' => []])]);

    app(SyncFloodAreas::class)->handle();
    $this->artisan('ingest:floods --quick')->assertSuccessful();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'arcgis.com'));
});
