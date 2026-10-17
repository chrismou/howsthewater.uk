<?php

namespace App\Actions\Ingest;

use App\Data\OverflowAnnualReturnData;
use App\Models\OverflowAnnualReturn;
use App\Models\OverflowMonitor;
use App\Services\Geo\Coordinates;
use App\Services\Geo\OverflowSiteName;
use App\Services\RiversTrust\AnnualReturnsClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Imports each configured year of the overflow annual returns (services.overflow_annual_returns), replacing that
 * year's rows, and matches each row to a monitor: by id where the return carries the live feeds' id (England from
 * 2023), otherwise by position, the nearest monitor within MATCH_METRES (Welsh rows carry no id, and 2021 and 2022
 * use other ids). Each monitor takes at most one row a year, the closest. Monitors whose feed gives no name then take
 * the site name from their latest return. A year that fails to read keeps its old rows.
 */
class ImportOverflowAnnualReturns
{
    /** Returns and live feeds place the same outlet within a few metres (Tintern Main Road, 1 m). */
    public const MATCH_METRES = 25;

    /** Grid cells for the position search, in degrees: about 110 m north to south. */
    private const CELL_DEGREES = 0.001;

    public function __construct(private AnnualReturnsClient $client)
    {
    }

    /**
     * @return array{years: int, rows: int, matched: int, failed: list<int>}
     */
    public function handle(): array
    {
        $totals = ['years' => 0, 'rows' => 0, 'matched' => 0, 'failed' => []];
        $monitors = OverflowMonitor::query()->get(['id', 'external_id', 'latitude', 'longitude']);
        $monitorIdsByExternalId = $monitors->pluck('id', 'external_id');
        $grid = $this->grid($monitors);

        foreach (config()->array('services.overflow_annual_returns') as $year => $url) {
            try {
                $rows = $this->client->rows((string) $url);
            } catch (Throwable $exception) {
                Log::error("Overflow annual returns for {$year} failed: {$exception->getMessage()}");
                $totals['failed'][] = (int) $year;

                continue;
            }

            $matches = $this->match($rows, $monitorIdsByExternalId, $grid);
            $this->replaceYear((int) $year, $rows, $matches);

            $totals['years']++;
            $totals['rows'] += $rows->count();
            $totals['matched'] += count($matches);
        }

        $this->nameMonitors();

        return $totals;
    }

    /**
     * Each row's monitor, keyed by the row's index: id matches first, then the closest position matches, one row per
     * monitor.
     *
     * @param  Collection<int, OverflowAnnualReturnData>  $rows
     * @param  Collection<string, int>  $monitorIdsByExternalId
     * @param  array<string, list<array{id: int, latitude: float, longitude: float}>>  $grid
     * @return array<int, int>
     */
    private function match(Collection $rows, Collection $monitorIdsByExternalId, array $grid): array
    {
        $candidates = [];

        foreach ($rows as $index => $row) {
            if ($row->uid !== null && $monitorIdsByExternalId->has($row->uid)) {
                $candidates[] = ['row' => $index, 'monitor' => $monitorIdsByExternalId[$row->uid], 'distance' => -1.0];

                continue;
            }

            $nearest = $this->nearest($row, $grid);

            if ($nearest !== null) {
                $candidates[] = ['row' => $index, ...$nearest];
            }
        }

        usort($candidates, fn (array $a, array $b) => $a['distance'] <=> $b['distance']);

        $matches = [];
        $taken = [];

        foreach ($candidates as $candidate) {
            if (isset($matches[$candidate['row']]) || isset($taken[$candidate['monitor']])) {
                continue;
            }

            $matches[$candidate['row']] = $candidate['monitor'];
            $taken[$candidate['monitor']] = true;
        }

        return $matches;
    }

    /**
     * @param  array<string, list<array{id: int, latitude: float, longitude: float}>>  $grid
     * @return array{monitor: int, distance: float}|null
     */
    private function nearest(OverflowAnnualReturnData $row, array $grid): ?array
    {
        if ($row->latitude === null || $row->longitude === null) {
            return null;
        }

        $best = null;
        $latitudeCell = (int) floor($row->latitude / self::CELL_DEGREES);
        $longitudeCell = (int) floor($row->longitude / self::CELL_DEGREES);

        for ($latitudeOffset = -1; $latitudeOffset <= 1; $latitudeOffset++) {
            for ($longitudeOffset = -1; $longitudeOffset <= 1; $longitudeOffset++) {
                foreach ($grid[($latitudeCell + $latitudeOffset).':'.($longitudeCell + $longitudeOffset)] ?? [] as $monitor) {
                    $distance = Coordinates::distanceMetres($row->latitude, $row->longitude, $monitor['latitude'], $monitor['longitude']);

                    if ($distance <= self::MATCH_METRES && ($best === null || $distance < $best['distance'])) {
                        $best = ['monitor' => $monitor['id'], 'distance' => $distance];
                    }
                }
            }
        }

        return $best;
    }

    /**
     * @param  Collection<int, OverflowMonitor>  $monitors
     * @return array<string, list<array{id: int, latitude: float, longitude: float}>>
     */
    private function grid(Collection $monitors): array
    {
        $grid = [];

        foreach ($monitors as $monitor) {
            $key = (int) floor($monitor->latitude / self::CELL_DEGREES).':'.(int) floor($monitor->longitude / self::CELL_DEGREES);
            $grid[$key][] = ['id' => $monitor->id, 'latitude' => $monitor->latitude, 'longitude' => $monitor->longitude];
        }

        return $grid;
    }

    /**
     * @param  Collection<int, OverflowAnnualReturnData>  $rows
     * @param  array<int, int>  $matches
     */
    private function replaceYear(int $year, Collection $rows, array $matches): void
    {
        $now = now();

        DB::transaction(function () use ($year, $rows, $matches, $now) {
            OverflowAnnualReturn::query()->where('year', $year)->delete();

            $rows->map(fn (OverflowAnnualReturnData $row, int $index) => [
                'year' => $year,
                'overflow_monitor_id' => $matches[$index] ?? null,
                'uid' => $row->uid,
                'company' => mb_substr($row->company, 0, 255),
                'site_name' => $row->siteName === null ? null : mb_substr($row->siteName, 0, 255),
                'permit_reference' => $row->permitReference === null ? null : mb_substr($row->permitReference, 0, 255),
                'counted_spills' => $row->countedSpills,
                'total_hours' => $row->totalHours,
                'monitor_uptime_percent' => $row->monitorUptimePercent,
                'receiving_water' => $row->receivingWater === null ? null : mb_substr($row->receivingWater, 0, 255),
                'latitude' => $row->latitude,
                'longitude' => $row->longitude,
                'created_at' => $now,
                'updated_at' => $now,
            ])->chunk(500)->each(fn (Collection $chunk) => OverflowAnnualReturn::query()->insert($chunk->values()->all()));
        });
    }

    /**
     * Gives each monitor its latest matched return's site name, tidied, and clears it where none matches any more.
     */
    private function nameMonitors(): void
    {
        $latestNames = OverflowAnnualReturn::query()
            ->whereNotNull('overflow_monitor_id')
            ->whereNotNull('site_name')
            ->orderBy('year')
            ->get(['overflow_monitor_id', 'site_name'])
            ->mapWithKeys(fn (OverflowAnnualReturn $row) => [$row->overflow_monitor_id => OverflowSiteName::tidy($row->site_name)]);

        OverflowMonitor::query()->get(['id', 'return_name'])->each(function (OverflowMonitor $monitor) use ($latestNames) {
            $name = $latestNames->get($monitor->id);

            if ($monitor->return_name !== $name) {
                $monitor->update(['return_name' => $name]);
            }
        });
    }
}
