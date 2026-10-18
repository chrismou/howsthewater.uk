<?php

use App\Actions\Ingest\ImportOverflowSpillLogs;
use App\Actions\Ingest\RefreshRegions;
use App\Data\SpillLogRowData;
use App\Enums\SpillLogSource;
use App\Models\OverflowMonitor;
use App\Models\OverflowSpillLog;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use App\Services\Conditions\SpillCoverage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * An XLSX file's contents, one sheet per list of rows.
 *
 * @param  list<list<list<string>>>  $sheets
 */
function spillLogSpreadsheet(array $sheets): string
{
    $base = (string) tempnam(sys_get_temp_dir(), 'spill-log-test-');
    unlink($base);
    $path = "{$base}.xlsx";
    $writer = SimpleExcelWriter::create($path)->noHeaderRow();

    foreach ($sheets as $index => $rows) {
        if ($index > 0) {
            $writer->addNewSheetAndMakeItCurrent();
        }

        $writer->addRows([['Unique ID', 'Site Name', 'Discharge Start (GMT)', 'Discharge Stop (GMT)'], ...$rows]);
    }

    $writer->close();
    $contents = (string) file_get_contents($path);
    unlink($path);

    return $contents;
}

/**
 * Fakes the Environment Agency dataset (files by name, each with a modified time and its contents) and Stream's
 * catalogue and log services (by service URL: title, fingerprint and rows of id, start ms, end ms). Calling it again
 * changes what they return, as Http::fake() keeps its first handler.
 *
 * @param  array<string, array{modified: string, contents: string, status?: int}>  $files
 * @param  array<string, array{title: string, fingerprint: string, rows: list<array{0: string, 1: int, 2: int}>, failFrom?: int}>  $services
 */
function fakeSpillLogSources(array $files, array $services = []): void
{
    $listing = collect($files)->map(fn (array $file, string $name) => ['name' => $name, 'size' => strlen($file['contents']), 'lastModified' => $file['modified']])->values();
    $page = '<html><body><script id="__NEXT_DATA__" type="application/json">'.json_encode(['props' => ['pageProps' => ['files' => $listing]]]).'</script></body></html>';
    $first = ! app()->bound('spill-log-sources');
    app()->instance('spill-log-sources', compact('files', 'services', 'page'));

    if (! $first) {
        return;
    }

    Http::fake(function (Request $request) {
        ['files' => $files, 'services' => $services, 'page' => $page] = app('spill-log-sources');
        $url = $request->url();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (str_contains($url, '/dataset/e9677ac1')) {
            return Http::response($page);
        }

        if (str_contains($url, '/api/file/download')) {
            $file = $files[$query['fileName']] ?? null;

            return $file === null ? Http::response('', 404) : Http::response($file['contents'], $file['status'] ?? 200);
        }

        if (str_contains($url, 'streamwaterdata.co.uk')) {
            return Http::response(['features' => collect($services)->map(fn (array $service, string $serviceUrl) => ['properties' => ['type' => 'Feature Service', 'title' => $service['title'], 'url' => $serviceUrl]])->values()]);
        }

        foreach ($services as $serviceUrl => $service) {
            $path = substr((string) strtok($url, '?'), strlen($serviceUrl));

            if (! str_starts_with($url, $serviceUrl)) {
                continue;
            }

            if ($path === '') {
                return Http::response(['layers' => [['id' => 0]]]);
            }

            if ($path === '/0') {
                return Http::response([
                    'name' => $service['title'],
                    'objectIdField' => 'OBJECTID',
                    ...($service['fingerprint'] === '' ? [] : ['editingInfo' => ['dataLastEditDate' => $service['fingerprint']]]),
                    'fields' => [['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID'], ['name' => 'SiteId', 'type' => 'esriFieldTypeString'], ['name' => 'EventStart', 'type' => 'esriFieldTypeDate'], ['name' => 'EventEnd', 'type' => 'esriFieldTypeDate']],
                ]);
            }

            $rows = collect($service['rows']);

            if (isset($query['outStatistics'])) {
                $starts = $rows->pluck(1)->filter(fn (int $start) => $start <= now()->getTimestampMs());

                return Http::response(['features' => [['attributes' => ['first_start' => $starts->min(), 'last_start' => $starts->max(), 'spills' => $starts->count()]]]]);
            }

            if (($query['outFields'] ?? '') === 'SiteId') {
                return Http::response(['features' => $rows->take(50)->map(fn (array $row) => ['attributes' => ['SiteId' => $row[0]]])->values()]);
            }

            preg_match("/TIMESTAMP '([^']+)'/", (string) ($query['where'] ?? ''), $since);
            $sinceMs = Carbon::parse($since[1] ?? '1970-01-01', 'UTC')->getTimestampMs();

            if (isset($service['failFrom']) && (int) ($query['resultOffset'] ?? 0) >= $service['failFrom']) {
                return Http::response('', 500);
            }

            $page = $rows->filter(fn (array $row) => $row[1] >= $sinceMs)->values()->slice((int) ($query['resultOffset'] ?? 0), 2);

            return Http::response([
                'features' => $page->map(fn (array $row) => ['attributes' => ['SiteId' => $row[0], 'EventStart' => $row[1], 'EventEnd' => $row[2]]])->values(),
                'exceededTransferLimit' => $page->count() === 2,
            ]);
        }

        return Http::response('', 404);
    });
}

function spillLogMs(string $time): int
{
    return Carbon::parse($time, 'UTC')->getTimestampMs();
}

/**
 * @return list<string>
 */
function spillTimes(OverflowMonitor $monitor): array
{
    return SpillEvent::query()->where('overflow_monitor_id', $monitor->id)->orderBy('started_at')->get()
        ->map(fn (SpillEvent $event) => $event->started_at->format('Y-m-d H:i').($event->isFromPublishedLog() ? ' log' : ' live'))
        ->all();
}

it('reads the times the published logs give in every format', function (mixed $cell, ?string $expected) {
    $timestamp = SpillLogRowData::timestamp($cell);

    expect($timestamp === null ? null : gmdate('Y-m-d H:i:s', $timestamp))->toBe($expected);
})->with([
    'ISO with milliseconds' => ['2025-01-01T04:05:00.000Z', '2025-01-01 04:05:00'],
    'ISO with seconds' => ['2025-07-20T13:51:59Z', '2025-07-20 13:51:59'],
    'slashed year first' => ['2024/01/01T20:14:32.000Z', '2024-01-01 20:14:32'],
    'day first' => ['20/07/2025 13:51', '2025-07-20 13:51:00'],
    'spreadsheet date' => [new DateTimeImmutable('2025-12-23 08:30:00'), '2025-12-23 08:30:00'],
    'Excel day number' => [45658.5, '2025-01-01 12:00:00'],
    'header' => ['Discharge Start (GMT)', null],
    'blank' => ['', null],
]);

it('imports the Environment Agency files for the time before each overflow\'s record, replacing the feed\'s first-sight copies', function () {
    $this->travelTo('2026-10-18 12:00:00');
    $first = OverflowMonitor::factory()->create(['external_id' => 'YWS00001', 'created_at' => '2026-10-14 09:00:00']);
    $second = OverflowMonitor::factory()->create(['external_id' => 'YWS00002', 'created_at' => '2026-10-14 09:00:00']);
    // What the feed showed when we first saw them: one inside a published year, one from before 2024, and our own record.
    SpillEvent::factory()->create(['overflow_monitor_id' => $first->id, 'started_at' => '2025-06-01 08:00:00', 'ended_at' => '2025-06-01 09:00:00']);
    SpillEvent::factory()->create(['overflow_monitor_id' => $second->id, 'started_at' => '2023-05-01 08:00:00', 'ended_at' => '2023-05-01 09:00:00']);
    SpillEvent::factory()->create(['overflow_monitor_id' => $first->id, 'started_at' => '2026-10-15 08:00:00', 'ended_at' => '2026-10-15 09:00:00']);

    fakeSpillLogSources([
        'Yorkshire 2025 Detailed EDM Data.csv' => ['modified' => '2026-05-08T00:00:00Z', 'contents' => "\u{FEFF}Unique ID,Site Name,Discharge Start (GMT),Discharge Stop (GMT)\nYWS00001,ELLERTON CSO,2025-06-01T08:05:00.000Z,2025-06-01T09:10:00.000Z\nYWS00002,OTHER CSO,2025/07/01T10:00:00.000Z,2025/07/01T11:30:00.000Z\nZZZ00009,UNKNOWN CSO,2025-08-01T10:00:00Z,2025-08-01T11:00:00Z\n"],
        // A ".csv" that is really a spreadsheet, as Thames Water's 2024 file is.
        'Yorkshire 2024 Detailed EDM Data.csv' => ['modified' => '2026-04-30T00:00:00Z', 'contents' => spillLogSpreadsheet([[['YWS00002', 'OTHER CSO', '2024-03-01T10:00:00Z', '2024-03-01T12:00:00Z']]])],
        // This year's months so far, a sheet a month, and a spill after the record start the feed already holds.
        'Yorkshire Water Detailed Data 2026.xlsx' => ['modified' => '2026-09-29T00:00:00Z', 'contents' => spillLogSpreadsheet([
            [['YWS00001', 'ELLERTON CSO', '2026-01-05T10:00:00Z', '2026-01-05T11:00:00Z']],
            [['YWS00001', 'ELLERTON CSO', '2026-07-20T10:00:00Z', '2026-07-20T10:30:00Z'], ['YWS00001', 'ELLERTON CSO', '2026-10-16T10:00:00Z', '2026-10-16T10:30:00Z']],
        ])],
        'Yorkshire 2023 Detailed EDM Data.csv' => ['modified' => '2026-04-30T00:00:00Z', 'contents' => "YWS00002,OTHER CSO,2023-03-01T10:00:00Z,2023-03-01T12:00:00Z\n"],
        'Technical Detail DSP April 2026.docx' => ['modified' => '2026-04-30T00:00:00Z', 'contents' => 'not a log'],
    ]);

    $this->artisan('ingest:overflow-spill-logs')
        ->expectsOutputToContain('Read 3 spill logs (0 unchanged): 5 spills imported, 1 rows for overflows we do not hold, 0 rejected as implausible.')
        ->assertSuccessful();

    expect(spillTimes($first))->toBe(['2025-06-01 08:05 log', '2026-01-05 10:00 log', '2026-07-20 10:00 log', '2026-10-15 08:00 live'])
        ->and(spillTimes($second))->toBe(['2023-05-01 08:00 live', '2024-03-01 10:00 log', '2025-07-01 10:00 log']);

    $partYear = OverflowSpillLog::query()->where('key', 'environment_agency:Yorkshire Water Detailed Data 2026.xlsx')->sole();
    expect($partYear->company)->toBe('Yorkshire Water')
        ->and($partYear->covers_from->toDateString())->toBe('2026-01-01')
        ->and($partYear->covers_until->toDateTimeString())->toBe('2026-11-01 00:00:00')
        ->and(OverflowSpillLog::query()->where('key', 'environment_agency:Yorkshire 2025 Detailed EDM Data.csv')->sole()->unmatched)->toBe(1);

    // Nothing has changed, so nothing is downloaded again.
    $downloads = fn () => collect(Http::recorded())->filter(fn (array $pair) => str_contains($pair[0]->url(), '/api/file/download'))->count();
    $before = $downloads();
    $this->artisan('ingest:overflow-spill-logs')->expectsOutputToContain('Read 0 spill logs (3 unchanged)')->assertSuccessful();
    expect($downloads())->toBe($before);
});

it('reads a corrected file again, replacing its spills', function () {
    $this->travelTo('2026-10-18 12:00:00');
    $monitor = OverflowMonitor::factory()->create(['external_id' => 'YWS00001', 'created_at' => '2026-10-14 09:00:00']);
    $file = fn (string $modified, string $day) => ['Yorkshire 2025 Detailed EDM Data.csv' => ['modified' => $modified, 'contents' => "YWS00001,ELLERTON CSO,{$day}T10:00:00Z,{$day}T11:00:00Z\n"]];

    fakeSpillLogSources($file('2026-05-08T00:00:00Z', '2025-03-01'));
    app(ImportOverflowSpillLogs::class)->handle();
    fakeSpillLogSources($file('2026-06-01T00:00:00Z', '2025-04-01'));
    app(ImportOverflowSpillLogs::class)->handle();

    expect(spillTimes($monitor))->toBe(['2025-04-01 10:00 log']);
});

it('fills the years the Environment Agency has not published from Stream, until its own file replaces them', function () {
    $this->travelTo('2026-10-18 12:00:00');
    Log::spy();
    $monitor = OverflowMonitor::factory()->create(['external_id' => 'AWS00001', 'company' => 'Anglian Water Services', 'created_at' => '2026-10-17 21:30:00']);
    $environmentAgency = ['Anglian Water Detailed Data 2025.csv' => ['modified' => '2026-07-17T00:00:00Z', 'contents' => "AWS00001,ABBOTSLEY SPS,2025-05-01T10:00:00Z,2025-05-01T11:00:00Z\n"]];
    $services = [
        'https://services-eu1.arcgis.com/test/arcgis/rest/services/March_EDM_for_Stream/FeatureServer' => ['title' => 'Anglian Water Event Duration Monitoring (Monthly March 2026)', 'fingerprint' => '1', 'rows' => [
            ['AWS00001', spillLogMs('2026-03-20 10:00'), spillLogMs('2026-03-20 11:00')],
            // The first 12 days come with day and month swapped: 10 March as 3 October, 12 March as 3 December.
            ['AWS00001', spillLogMs('2026-10-03 05:00'), spillLogMs('2026-10-03 06:00')],
            ['AWS00001', spillLogMs('2026-12-03 07:00'), spillLogMs('2026-12-03 08:00')],
        ]],
        // 2025 is the Environment Agency's, so Stream's copy of it is not used.
        'https://services-eu1.arcgis.com/test/arcgis/rest/services/April_to_October_EDM_for_STREAM/FeatureServer' => ['title' => 'Anglian Water Event Duration Monitoring (Monthly April 2025 to October 2025)', 'fingerprint' => '1', 'rows' => [
            ['AWS00001', spillLogMs('2025-05-01 10:00'), spillLogMs('2025-05-01 11:00')],
        ]],
    ];

    fakeSpillLogSources($environmentAgency, $services);
    app(ImportOverflowSpillLogs::class)->handle();

    $march = OverflowSpillLog::query()->where('source', SpillLogSource::Stream)->where('name', 'like', '%March 2026%')->sole();
    expect(spillTimes($monitor))->toBe(['2025-05-01 10:00 log', '2026-03-10 05:00 log', '2026-03-12 07:00 log', '2026-03-20 10:00 log'])
        ->and($march->covers_from->toDateString())->toBe('2026-03-01')
        ->and($march->covers_until->toDateTimeString())->toBe('2026-03-31 23:00:00')
        ->and(OverflowSpillLog::query()->where('name', 'like', '%April 2025%')->sole()->covers_from)->toBeNull();

    fakeSpillLogSources([...$environmentAgency, 'Anglian Water Detailed Data 2026.csv' => ['modified' => '2027-05-01T00:00:00Z', 'contents' => "AWS00001,ABBOTSLEY SPS,2026-03-20T10:00:00Z,2026-03-20T11:00:00Z\n"]], $services);
    app(ImportOverflowSpillLogs::class)->handle();

    expect(spillTimes($monitor))->toBe(['2025-05-01 10:00 log', '2026-03-20 10:00 log'])
        ->and(SpillEvent::query()->where('overflow_spill_log_id', $march->id)->count())->toBe(0);
    Log::shouldHaveReceived('warning')->once();
});

it('keeps the other files when one fails, and says which', function () {
    $this->travelTo('2026-10-18 12:00:00');
    Sleep::fake();
    Log::spy();
    $monitor = OverflowMonitor::factory()->create(['external_id' => 'YWS00001', 'created_at' => '2026-10-14 09:00:00']);

    fakeSpillLogSources([
        'Yorkshire 2024 Detailed EDM Data.csv' => ['modified' => '2026-04-30T00:00:00Z', 'contents' => 'Server error', 'status' => 500],
        'Yorkshire 2025 Detailed EDM Data.csv' => ['modified' => '2026-05-08T00:00:00Z', 'contents' => "YWS00001,ELLERTON CSO,2025-03-01T10:00:00Z,2025-03-01T11:00:00Z\n"],
    ]);

    $this->artisan('ingest:overflow-spill-logs')
        ->expectsOutputToContain('Yorkshire 2024 Detailed EDM Data.csv failed')
        ->assertFailed();

    expect(spillTimes($monitor))->toBe(['2025-03-01 10:00 log'])
        ->and(OverflowSpillLog::query()->where('key', 'environment_agency:Yorkshire 2024 Detailed EDM Data.csv')->value('fingerprint'))->toBeNull();
    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'Yorkshire 2024 Detailed EDM Data.csv'))->once();
});

it('charts published months beside the live record, says where each comes from and which months are missing', function () {
    $this->travelTo('2026-10-18 12:00:00');
    $region = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Wharfe', 'slug' => 'wharfe']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ilkley', 'slug' => 'ilkley', 'river_metres' => 50000]);
    $monitor = OverflowMonitor::factory()->create(['external_id' => 'YWS00001', 'river_id' => $river->id, 'river_metres' => 45000, 'created_at' => '2026-10-14 09:00:00']);
    // Covered time runs between UK months: August starts at 23:00 GMT.
    $log = OverflowSpillLog::factory()->create(['company' => 'Yorkshire Water', 'year' => 2026, 'covers_from' => '2026-01-01 00:00:00', 'covers_until' => '2026-07-31 23:00:00']);
    OverflowSpillLog::factory()->create(['company' => 'Yorkshire Water', 'year' => 2025, 'covers_from' => '2025-01-01 00:00:00', 'covers_until' => '2026-01-01 00:00:00']);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'overflow_spill_log_id' => $log->id, 'started_at' => '2026-03-10 08:00:00', 'ended_at' => '2026-03-10 10:00:00']);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2026-10-16 08:00:00', 'ended_at' => '2026-10-16 09:00:00']);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('overflows.show', $monitor))
        ->assertOk()
        ->assertSeeInOrder(['2026 so far', '2', 'spills, 3.0 h'])
        ->assertSee('3.0 hours of spilling over the 10 months with data')
        ->assertSee('Spills before 14 October 2026 come from Yorkshire Water&#039;s own published records, via the Environment Agency.', false)
        ->assertSee('Yorkshire Water has not published 1 August to 13 October 2026 yet.')
        ->assertSeeInOrder(['Spills and rain per month', 'March 2026', '1', '2.0', 'August 2026', 'no data', 'September 2026', 'no data', 'October 2026 so far', '1', '1.0'])
        ->assertSee("from Yorkshire Water's published records before that", false);

    $this->get(route('spots.spills', [$region, $river, $spot]))
        ->assertOk()
        ->assertSee('since 1 January 2025')
        ->assertSeeInOrder(['By month', 'October 2026 so far', 'September 2026', 'No data', 'August 2026', 'No data', 'July 2026'])
        ->assertSee('Yorkshire Water has not published 1 August to 13 October 2026 yet.');
});

it('says so where the company publishes no past spills, leaving the feed\'s first-sight spill out of the figures', function () {
    $this->travelTo('2026-10-18 12:00:00');
    $region = Region::factory()->create();
    $river = River::factory()->create(['region_id' => $region->id]);
    Spot::factory()->create(['river_id' => $river->id, 'river_metres' => 50000]);
    $monitor = OverflowMonitor::factory()->create(['external_id' => 'SWC00001', 'company' => 'Scottish Water', 'river_id' => $river->id, 'river_metres' => 45000, 'created_at' => '2026-10-16 06:30:00']);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2026-03-10 08:00:00', 'ended_at' => '2026-03-10 10:00:00']);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('overflows.show', $monitor))
        ->assertOk()
        ->assertSee('Scottish Water does not publish past spills, so its spills here start when we started reading its live feed on 16 October 2026.')
        ->assertSee('0.0 hours of spilling over the 1 month with data')
        ->assertSeeInOrder(['Spills and rain per month', 'March 2026', 'no data']);
});

it('leaves spills imported from a published log out of the feeds', function () {
    $this->travelTo('2026-10-18 12:00:00');
    $region = Region::factory()->create();
    $river = River::factory()->create(['region_id' => $region->id]);
    Spot::factory()->create(['river_id' => $river->id, 'river_metres' => 50000]);
    $monitor = OverflowMonitor::factory()->create(['river_id' => $river->id, 'river_metres' => 45000, 'created_at' => '2026-10-14 09:00:00']);
    $log = OverflowSpillLog::factory()->create(['covers_from' => '2026-01-01 00:00:00', 'covers_until' => '2026-10-14 00:00:00']);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'overflow_spill_log_id' => $log->id, 'started_at' => '2026-10-01 08:00:00', 'ended_at' => '2026-10-01 10:00:00']);
    $live = SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2026-10-16 08:00:00', 'ended_at' => '2026-10-16 09:00:00']);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('overflows.feed', $monitor))
        ->assertOk()
        ->assertSee("spill-{$live->id}-start")
        ->assertDontSee('1 Oct, 09:00');
});

it('reads file names with underscores, and notes one that names no year', function () {
    $this->travelTo('2026-10-18 12:00:00');
    Log::spy();
    $monitor = OverflowMonitor::factory()->create(['external_id' => 'YWS00001', 'created_at' => '2026-10-14 09:00:00']);

    fakeSpillLogSources([
        'Yorkshire_Water_2025_Detailed_EDM_Data.csv' => ['modified' => '2026-05-08T00:00:00Z', 'contents' => "YWS00001,ELLERTON CSO,2025-03-01T10:00:00Z,2025-03-01T11:00:00Z\n"],
        'Yorkshire Detailed EDM Data.csv' => ['modified' => '2026-05-08T00:00:00Z', 'contents' => "YWS00001,ELLERTON CSO,2025-04-01T10:00:00Z,2025-04-01T11:00:00Z\n"],
    ]);

    $this->artisan('ingest:overflow-spill-logs')->assertSuccessful();

    expect(spillTimes($monitor))->toBe(['2025-03-01 10:00 log']);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'Yorkshire Detailed EDM Data.csv names no year'))->once();
});

it('rejects spills that cannot be real, and leaves rows from another year to that year\'s file', function () {
    $this->travelTo('2026-10-18 12:00:00');
    $monitor = OverflowMonitor::factory()->create(['external_id' => 'YWS00001', 'created_at' => '2026-10-14 09:00:00']);

    fakeSpillLogSources([
        'Yorkshire 2025 Detailed EDM Data.csv' => ['modified' => '2026-05-08T00:00:00Z', 'contents' => implode("\n", [
            'YWS00001,ELLERTON CSO,2025-03-01T10:00:00Z,2025-03-01T11:00:00Z',
            // A typo in the year of its stop, and a row from the year before.
            'YWS00001,ELLERTON CSO,2025-05-01T10:00:00Z,2095-05-01T11:00:00Z',
            'YWS00001,ELLERTON CSO,2024-12-31T23:30:00Z,2025-01-01T00:30:00Z',
        ])],
    ]);

    $this->artisan('ingest:overflow-spill-logs')->expectsOutputToContain('1 spills imported, 0 rows for overflows we do not hold, 1 rejected as implausible.')->assertSuccessful();

    expect(spillTimes($monitor))->toBe(['2025-03-01 10:00 log']);
});

it('only reads Stream logs on the allowed hosts', function () {
    $this->travelTo('2026-10-18 12:00:00');
    OverflowMonitor::factory()->create(['external_id' => 'AWS00001', 'company' => 'Anglian Water Services', 'created_at' => '2026-10-17 21:30:00']);

    fakeSpillLogSources([], [
        'https://internal.example/arcgis/rest/services/EDM/FeatureServer' => ['title' => 'Anglian Water Event Duration Monitoring 2026', 'fingerprint' => '1', 'rows' => [['AWS00001', spillLogMs('2026-03-20 10:00'), spillLogMs('2026-03-20 11:00')]]],
    ]);
    app(ImportOverflowSpillLogs::class)->handle();

    expect(OverflowSpillLog::query()->count())->toBe(0)
        ->and(collect(Http::recorded())->filter(fn (array $pair) => str_contains($pair[0]->url(), 'internal.example'))->count())->toBe(0);
});

it('claims no published months for a log that fails part way, and reads a log with no edit date again only when it changes', function () {
    $this->travelTo('2026-10-18 12:00:00');
    Sleep::fake();
    $monitor = OverflowMonitor::factory()->create(['external_id' => 'WXW00001', 'company' => 'Wessex Water', 'created_at' => '2026-10-17 21:30:00']);
    $rows = [
        ['WXW00001', spillLogMs('2026-02-01 10:00'), spillLogMs('2026-02-01 11:00')],
        ['WXW00001', spillLogMs('2026-03-01 10:00'), spillLogMs('2026-03-01 11:00')],
        ['WXW00001', spillLogMs('2026-04-01 10:00'), spillLogMs('2026-04-01 11:00')],
    ];
    $url = 'https://services.arcgis.com/test/arcgis/rest/services/Wessex_2026/FeatureServer';

    fakeSpillLogSources([], [$url => ['title' => 'Wessex Water Event Duration Monitoring Monthly 2026', 'fingerprint' => '', 'rows' => $rows, 'failFrom' => 2]]);
    app(ImportOverflowSpillLogs::class)->handle();

    expect(OverflowSpillLog::query()->sole()->covers_from)->toBeNull()
        ->and(app(SpillCoverage::class)->intervals('Wessex Water', $monitor->created_at->getTimestamp()))->toHaveCount(1);

    fakeSpillLogSources([], [$url => ['title' => 'Wessex Water Event Duration Monitoring Monthly 2026', 'fingerprint' => '', 'rows' => $rows]]);
    app(ImportOverflowSpillLogs::class)->handle();
    $log = OverflowSpillLog::query()->sole();
    $readAt = $log->read_at;

    expect(spillTimes($monitor))->toBe(['2026-02-01 10:00 log', '2026-03-01 10:00 log', '2026-04-01 10:00 log'])
        ->and($log->covers_from)->not->toBeNull();

    $this->travel(1)->days();
    app(ImportOverflowSpillLogs::class)->handle();

    expect(OverflowSpillLog::query()->sole()->read_at->equalTo($readAt))->toBeTrue();
});

it('names a month missing between published ones, and says nothing about publishing for a company with no log yet', function () {
    $this->travelTo('2026-10-18 12:00:00');
    $coverage = fn () => app(SpillCoverage::class);
    OverflowSpillLog::factory()->create(['company' => 'Wessex Water', 'covers_from' => '2026-01-01 00:00:00', 'covers_until' => '2026-03-01 00:00:00']);
    OverflowSpillLog::factory()->create(['company' => 'Wessex Water', 'covers_from' => '2026-03-31 23:00:00', 'covers_until' => '2026-09-30 23:00:00']);
    $recordStart = Carbon::parse('2026-10-17 21:30:00', 'UTC')->getTimestamp();
    $window = [Carbon::parse('2025-10-31 23:59:59', 'UTC')->getTimestamp(), now()->getTimestamp()];

    expect($coverage()->notes(['Wessex Water' => $recordStart], ...$window))->toBe([
        'Spills before 17 October 2026 come from Wessex Water\'s own published records, via the Environment Agency. From then on they come from its live feed, which we read every 15 minutes. The two are recorded differently, so they may not match exactly.',
        'We hold no spills from Wessex Water for 1 March to 31 March 2026.',
        'Wessex Water has not published 1 October to 16 October 2026 yet.',
    ])->and($coverage()->notes(['Southern Water' => $recordStart], ...$window))->toBe([
        'Spills from Southern Water here start when we started reading its live feed on 17 October 2026.',
    ]);
});

it('never adds back a first-sight spill a published log replaced while it stays the feed\'s latest', function () {
    $this->travelTo('2026-10-18 12:00:00');
    $monitor = OverflowMonitor::factory()->create(['external_id' => 'YWS00001', 'created_at' => '2026-10-14 09:00:00', 'latest_event_started_at' => '2025-08-01 08:00:00', 'latest_event_ended_at' => '2025-08-01 09:00:00']);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2025-08-01 08:00:00', 'ended_at' => '2025-08-01 09:00:00']);
    // The log times the same spill half a minute later.
    fakeSpillLogSources(['Yorkshire 2025 Detailed EDM Data.csv' => ['modified' => '2026-05-08T00:00:00Z', 'contents' => "YWS00001,ELLERTON CSO,2025-08-01T08:00:30Z,2025-08-01T09:00:00Z\n"]]);
    app(ImportOverflowSpillLogs::class)->handle();

    app(\App\Actions\Ingest\SyncOverflowMonitors::class)->sync(collect([new \App\Data\OverflowMonitorData(
        externalId: 'YWS00001',
        company: 'Yorkshire Water',
        receivingWatercourse: null,
        latitude: $monitor->latitude,
        longitude: $monitor->longitude,
        status: \App\Enums\OverflowStatus::NotDischarging,
        statusStartedAt: Carbon::parse('2025-08-01 09:00:00', 'UTC'),
        latestEventStartedAt: Carbon::parse('2025-08-01 08:00:00', 'UTC'),
        latestEventEndedAt: Carbon::parse('2025-08-01 09:00:00', 'UTC'),
        sourceUpdatedAt: now(),
    )]));

    expect(spillTimes($monitor))->toBe(['2025-08-01 08:00 log']);
});

it('reads a log again for overflows first seen since it was read, and says when a file has nothing it can read', function () {
    $this->travelTo('2026-10-18 12:00:00');
    Log::spy();
    OverflowMonitor::factory()->create(['external_id' => 'YWS00001', 'created_at' => '2026-10-14 09:00:00']);
    $files = [
        'Yorkshire 2025 Detailed EDM Data.csv' => ['modified' => '2026-05-08T00:00:00Z', 'contents' => "YWS00001,ELLERTON CSO,2025-03-01T10:00:00Z,2025-03-01T11:00:00Z\nYWS00002,OTHER CSO,2025-12-01T10:00:00Z,2025-12-01T11:00:00Z\n"],
        // Laid out differently, so nothing in it can be read.
        'Severn Trent 2025 Detailed EDM Data.csv' => ['modified' => '2026-05-08T00:00:00Z', 'contents' => "Permit,Unique ID,Start,Stop\nX1,SVT00001,soon,later\n"],
    ];
    fakeSpillLogSources($files);
    app(ImportOverflowSpillLogs::class)->handle();

    $this->travel(1)->days();
    $later = OverflowMonitor::factory()->create(['external_id' => 'YWS00002', 'created_at' => now()]);
    $result = app(ImportOverflowSpillLogs::class)->handle();

    expect(spillTimes($later))->toBe(['2025-12-01 10:00 log'])
        ->and($result['failed'])->toBe(['Severn Trent 2025 Detailed EDM Data.csv'])
        ->and(OverflowSpillLog::query()->where('name', 'like', 'Severn Trent%')->value('fingerprint'))->toBeNull();
    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'Severn Trent 2025 Detailed EDM Data.csv failed: no readable rows'))->twice();
});

it('drops a file the Environment Agency no longer lists, with its spills', function () {
    $this->travelTo('2026-10-18 12:00:00');
    $monitor = OverflowMonitor::factory()->create(['external_id' => 'YWS00001', 'created_at' => '2026-10-14 09:00:00']);
    $row = "YWS00001,ELLERTON CSO,2025-03-01T10:00:00Z,2025-03-01T11:00:00Z\n";
    fakeSpillLogSources(['Yorkshire 2025 Detailed EDM Data.csv' => ['modified' => '2026-05-08T00:00:00Z', 'contents' => $row]]);
    app(ImportOverflowSpillLogs::class)->handle();

    // Reissued under another name: its spills must not be skipped as copies of the old file's.
    fakeSpillLogSources(['Yorkshire Water 2025 Detailed EDM Data v2.csv' => ['modified' => '2026-06-08T00:00:00Z', 'contents' => $row]]);
    app(ImportOverflowSpillLogs::class)->handle();

    expect(spillTimes($monitor))->toBe(['2025-03-01 10:00 log'])
        ->and(OverflowSpillLog::query()->pluck('name')->all())->toBe(['Yorkshire Water 2025 Detailed EDM Data v2.csv']);
});

it('swaps back a stop dated weeks after its start in a monthly log', function () {
    $this->travelTo('2026-10-18 12:00:00');
    $monitor = OverflowMonitor::factory()->create(['external_id' => 'AWS00001', 'company' => 'Anglian Water Services', 'created_at' => '2026-10-17 21:30:00']);
    fakeSpillLogSources([], [
        'https://services-eu1.arcgis.com/test/arcgis/rest/services/May_to_June_EDM_for_STREAM/FeatureServer' => ['title' => 'Anglian Water Event Duration Monitoring (Monthly May and June 2026)', 'fingerprint' => '1', 'rows' => [
            // A stop of 9 July stored as 7 September: over two months after its start, eleven days once swapped back.
            ['AWS00001', spillLogMs('2026-06-28 22:00'), spillLogMs('2026-09-07 02:00')],
        ]],
    ]);
    app(ImportOverflowSpillLogs::class)->handle();

    expect(SpillEvent::query()->where('overflow_monitor_id', $monitor->id)->sole()->ended_at->toDateTimeString())->toBe('2026-07-09 02:00:00');
});

it('leaves a first-sight spill outside the time it holds out of the overflow page\'s last 30 days', function () {
    $this->travelTo('2026-10-18 12:00:00');
    $region = Region::factory()->create();
    $river = River::factory()->create(['region_id' => $region->id]);
    Spot::factory()->create(['river_id' => $river->id, 'river_metres' => 50000]);
    $monitor = OverflowMonitor::factory()->create(['company' => 'Scottish Water', 'river_id' => $river->id, 'river_metres' => 45000, 'created_at' => '2026-10-16 06:30:00']);
    SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2026-10-05 08:00:00', 'ended_at' => '2026-10-05 10:00:00']);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('overflows.show', $monitor))
        ->assertOk()
        ->assertSeeInOrder(['Last 30 days', '0', 'Days with a spill, last 30', '0', 'Longest spill', '-']);
});
