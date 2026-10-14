<?php

namespace App\Services\Conditions;

use App\Data\NearbyMonitorSummary;
use App\Models\OverflowMonitor;
use App\Services\Geo\Coordinates;
use Illuminate\Support\Collection;

/**
 * Finds overflow monitors within a straight-line distance of any of a set
 * of points, for water bodies with no upstream: the sea and lakes.
 */
class NearbyMonitorFinder
{
    private const METRES_PER_DEGREE_LATITUDE = 111320;

    /**
     * @param  array<int, array{0: float, 1: float}>  $points  [lat, lng] pairs
     * @return Collection<int, NearbyMonitorSummary> Nearest first
     */
    public function near(array $points, int $radiusMetres): Collection
    {
        if ($points === []) {
            return collect();
        }

        $latitudes = array_column($points, 0);
        $longitudes = array_column($points, 1);
        $latitudePadding = $radiusMetres / self::METRES_PER_DEGREE_LATITUDE;
        $longitudePadding = $radiusMetres / (self::METRES_PER_DEGREE_LATITUDE * cos(deg2rad(max(array_map('abs', $latitudes)))));

        return OverflowMonitor::query()
            ->whereBetween('latitude', [min($latitudes) - $latitudePadding, max($latitudes) + $latitudePadding])
            ->whereBetween('longitude', [min($longitudes) - $longitudePadding, max($longitudes) + $longitudePadding])
            ->with(['river' => fn ($query) => $query->withoutCentreline(), 'spillEvents' => fn ($query) => $query->limit(1)])
            ->get()
            ->map(function (OverflowMonitor $monitor) use ($points) {
                $distance = min(array_map(fn (array $point) => Coordinates::distanceMetres($point[0], $point[1], $monitor->latitude, $monitor->longitude), $points));

                return new NearbyMonitorSummary(
                    monitor: $monitor,
                    distanceMetres: (int) round($distance),
                    status: $monitor->status,
                    latestEvent: $monitor->spillEvents->first(),
                );
            })
            ->filter(fn (NearbyMonitorSummary $summary) => $summary->distanceMetres <= $radiusMetres)
            ->sortBy('distanceMetres')
            ->values();
    }
}
