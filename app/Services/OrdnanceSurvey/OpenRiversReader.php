<?php

namespace App\Services\OrdnanceSurvey;

use App\Services\Geo\BritishNationalGrid;
use App\Services\Osm\WayChainer;
use PDO;
use RuntimeException;

/**
 * Reads named watercourse links from the OS Open Rivers GeoPackage and
 * chains them into a single downstream polyline in WGS84.
 */
class OpenRiversReader
{
    public function __construct(private string $geoPackagePath)
    {
        if (! is_file($geoPackagePath)) {
            throw new RuntimeException("OS Open Rivers GeoPackage not found at {$geoPackagePath}.");
        }
    }

    /**
     * @return array<int, array{0: float, 1: float}> Ordered [lat, lng] pairs from source to mouth
     */
    public function riverCentreline(string $watercourseName): array
    {
        $pdo = new PDO('sqlite:'.$this->geoPackagePath, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $statement = $pdo->prepare(
            'SELECT id, geometry, flow_direction, start_node, end_node FROM watercourse_link WHERE watercourse_name = :name'
        );
        $statement->execute(['name' => $watercourseName]);

        $links = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $points = $this->decodeLineString($row['geometry']);

            if ($row['flow_direction'] === 'in opposite direction') {
                $points = array_reverse($points);
                [$row['start_node'], $row['end_node']] = [$row['end_node'], $row['start_node']];
            }

            $links[$row['id']] = ['start' => $row['start_node'], 'end' => $row['end_node'], 'points' => $points];
        }

        if ($links === []) {
            throw new RuntimeException("No OS Open Rivers links named {$watercourseName}.");
        }

        return WayChainer::chain($this->chainByTopology($links));
    }

    /**
     * Follows start/end node ids to build contiguous runs of links. The
     * runs are then handed to the proximity chainer to bridge any gaps
     * where the main stem briefly carries a different name.
     *
     * @param  array<string, array{start: string, end: string, points: array<int, array{0: float, 1: float}>}>  $links
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    private function chainByTopology(array $links): array
    {
        $byStart = [];

        foreach ($links as $id => $link) {
            $byStart[$link['start']][] = $id;
        }

        $endNodes = array_flip(array_column($links, 'end'));
        $visited = [];
        $runs = [];

        $sources = array_keys(array_filter($links, fn (array $link) => ! isset($endNodes[$link['start']])));

        foreach ([...$sources, ...array_keys($links)] as $startId) {
            if (isset($visited[$startId])) {
                continue;
            }

            $run = [];
            $currentId = $startId;

            while ($currentId !== null && ! isset($visited[$currentId])) {
                $visited[$currentId] = true;
                $link = $links[$currentId];
                $run = $run === [] ? $link['points'] : [...$run, ...array_slice($link['points'], 1)];

                $nextIds = array_values(array_filter($byStart[$link['end']] ?? [], fn (string $id) => ! isset($visited[$id])));
                usort($nextIds, fn (string $a, string $b) => count($links[$b]['points']) <=> count($links[$a]['points']));

                $currentId = $nextIds[0] ?? null;
            }

            if ($run !== []) {
                $runs[] = $run;
            }
        }

        return $runs;
    }

    /**
     * Decodes a GeoPackage binary blob holding a WKB LineString in BNG.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    private function decodeLineString(string $blob): array
    {
        if (substr($blob, 0, 2) !== 'GP') {
            throw new RuntimeException('Unexpected GeoPackage geometry header.');
        }

        $flags = ord($blob[3]);
        $envelopeSizes = [0, 32, 48, 48, 64];
        $envelopeSize = $envelopeSizes[($flags >> 1) & 0x07] ?? 0;

        $offset = 8 + $envelopeSize;
        $littleEndian = ord($blob[$offset]) === 1;
        $offset += 1;

        $format = $littleEndian ? 'V' : 'N';
        $geometryType = unpack($format, substr($blob, $offset, 4))[1] & 0xFF;
        $offset += 4;

        if ($geometryType !== 2) {
            throw new RuntimeException("Expected a LineString geometry, got type {$geometryType}.");
        }

        $pointCount = unpack($format, substr($blob, $offset, 4))[1];
        $offset += 4;

        $doubleFormat = $littleEndian ? 'e' : 'E';
        $points = [];

        for ($i = 0; $i < $pointCount; $i++) {
            $easting = unpack($doubleFormat, substr($blob, $offset, 8))[1];
            $northing = unpack($doubleFormat, substr($blob, $offset + 8, 8))[1];
            $offset += 16;

            $points[] = BritishNationalGrid::toLatLng($easting, $northing);
        }

        return $points;
    }
}
