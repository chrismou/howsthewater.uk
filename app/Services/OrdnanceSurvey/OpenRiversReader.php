<?php

namespace App\Services\OrdnanceSurvey;

use App\Services\Geo\BritishNationalGrid;
use App\Services\Geo\Coordinates;
use App\Services\Geo\PolylineProjector;
use App\Services\Osm\WayChainer;
use PDO;
use RuntimeException;
use SplPriorityQueue;

/**
 * Reads named watercourse links from the OS Open Rivers GeoPackage and
 * chains them into a single downstream polyline in WGS84.
 */
class OpenRiversReader
{
    /**
     * The widest gap between two sets of links (a reservoir, or a reach that
     * carries another name) still treated as one river.
     */
    private const GAP_METRES = 4000;

    public function __construct(private string $geoPackagePath)
    {
        if (! is_file($geoPackagePath)) {
            throw new RuntimeException("OS Open Rivers GeoPackage not found at {$geoPackagePath}.");
        }
    }

    /**
     * Reservoirs and brief changes of name split a river into several
     * disconnected sets of links, and unrelated rivers across Britain can
     * share a name. The caller passes either the parent river's centreline
     * (for a tributary) or an approximate mouth (for a main river); the set
     * whose mouth lies nearest is used as the seed, then any other set that
     * ends within GAP_METRES of the seed's source, or starts within it of
     * the seed's mouth, is joined on. With no hint every link is chained.
     *
     * @param  array<int, array{0: float, 1: float}>|null  $flowsInto  The parent river's centreline, if any
     * @param  array{0: float, 1: float}|null  $mouthHint  Roughly where the river ends, as [lat, lng]
     * @return array<int, array{0: float, 1: float}> Ordered [lat, lng] pairs from source to mouth
     */
    public function riverCentreline(string $watercourseName, ?array $flowsInto = null, ?array $mouthHint = null): array
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

        if ($flowsInto === null && $mouthHint === null) {
            return WayChainer::chain($this->chainByTopology($links));
        }

        $parent = $flowsInto !== null ? new PolylineProjector($flowsInto) : null;

        $distanceToMouth = $parent !== null
            ? fn (array $mouth) => $parent->project($mouth[0], $mouth[1])->offsetMetres
            : fn (array $mouth) => Coordinates::distanceMetres($mouth[0], $mouth[1], $mouthHint[0], $mouthHint[1]);

        $chains = array_map(fn (array $component) => $this->mainStem($component, $distanceToMouth), $this->connectedComponents($links));

        return $this->joinAcrossGaps($chains, $this->nearestMouth($chains, $distanceToMouth));
    }

    /**
     * @param  array<int, array<int, array{0: float, 1: float}>>  $chains
     * @param  callable(array{0: float, 1: float}): float  $distanceToMouth
     */
    private function nearestMouth(array $chains, callable $distanceToMouth): int
    {
        $best = 0;
        $bestDistance = INF;

        foreach ($chains as $index => $chain) {
            $distance = $distanceToMouth(end($chain));

            if ($distance < $bestDistance) {
                $best = $index;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * Extends the seed chain upstream and downstream with any other chain
     * that ends or starts close enough to count as the same river.
     *
     * @param  array<int, array<int, array{0: float, 1: float}>>  $chains
     * @return array<int, array{0: float, 1: float}>
     */
    private function joinAcrossGaps(array $chains, int $seedIndex): array
    {
        $chain = $chains[$seedIndex];
        unset($chains[$seedIndex]);

        do {
            $joined = false;

            foreach ($chains as $index => $candidate) {
                $source = $chain[0];
                $mouth = end($chain);

                if ($this->distance(end($candidate), $source) <= self::GAP_METRES) {
                    $chain = [...$candidate, ...$chain];
                } elseif ($this->distance($candidate[0], $mouth) <= self::GAP_METRES) {
                    $chain = [...$chain, ...$candidate];
                } else {
                    continue;
                }

                unset($chains[$index]);
                $joined = true;
            }
        } while ($joined && $chains !== []);

        return array_values($chain);
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    private function distance(array $a, array $b): float
    {
        return Coordinates::distanceMetres($a[0], $a[1], $b[0], $b[1]);
    }

    /**
     * Groups links by shared start/end node ids.
     *
     * @param  array<string, array{start: string, end: string, points: array<int, array{0: float, 1: float}>}>  $links
     * @return array<int, array<string, array{start: string, end: string, points: array<int, array{0: float, 1: float}>}>>
     */
    private function connectedComponents(array $links): array
    {
        $byNode = [];

        foreach ($links as $id => $link) {
            $byNode[$link['start']][] = $id;
            $byNode[$link['end']][] = $id;
        }

        $visited = [];
        $components = [];

        foreach (array_keys($links) as $seedId) {
            if (isset($visited[$seedId])) {
                continue;
            }

            $component = [];
            $queue = [$seedId];

            while ($queue !== []) {
                $id = array_pop($queue);

                if (isset($visited[$id])) {
                    continue;
                }

                $visited[$id] = true;
                $component[$id] = $links[$id];

                foreach ([$links[$id]['start'], $links[$id]['end']] as $node) {
                    foreach ($byNode[$node] as $neighbourId) {
                        if (! isset($visited[$neighbourId])) {
                            $queue[] = $neighbourId;
                        }
                    }
                }
            }

            $components[] = $component;
        }

        return $components;
    }

    /**
     * The main stem of one connected set of links, from source to mouth.
     * The mouth is the point where flow ends (a node nothing flows out of)
     * nearest the parent river or mouth hint. Recorded flow directions are
     * then ignored, since braided reaches (lakes, mill cuts) often have a
     * link drawn the wrong way round, which would cut a flow-following walk
     * short. The source is the node furthest from the mouth along the links,
     * and the stem is the shortest path between the two, which also
     * collapses parallel channels to one line.
     *
     * @param  array<string, array{start: string, end: string, points: array<int, array{0: float, 1: float}>}>  $links
     * @param  callable(array{0: float, 1: float}): float  $distanceToMouth
     * @return array<int, array{0: float, 1: float}>
     */
    private function mainStem(array $links, callable $distanceToMouth): array
    {
        $positions = [];
        $edges = [];

        foreach ($links as $id => $link) {
            $positions[$link['start']] = $link['points'][0];
            $positions[$link['end']] = end($link['points']);
            $length = $this->linkLength($link['points']);
            $edges[$link['start']][] = ['node' => $link['end'], 'link' => $id, 'length' => $length];
            $edges[$link['end']][] = ['node' => $link['start'], 'link' => $id, 'length' => $length];
        }

        $startNodes = array_flip(array_column($links, 'start'));
        $sinks = array_diff_key($positions, $startNodes) ?: $positions;

        $mouth = array_keys($sinks)[0];
        $mouthDistance = INF;

        foreach ($sinks as $node => $position) {
            $distance = $distanceToMouth($position);

            if ($distance < $mouthDistance) {
                $mouth = $node;
                $mouthDistance = $distance;
            }
        }

        // Dijkstra from the mouth; $towardsMouth records each node's next step downstream.
        $distances = [$mouth => 0.0];
        $towardsMouth = [];
        $queue = new SplPriorityQueue();
        $queue->insert($mouth, 0.0);

        while (! $queue->isEmpty()) {
            $node = $queue->extract();

            foreach ($edges[$node] as $edge) {
                $distance = $distances[$node] + $edge['length'];

                if ($distance < ($distances[$edge['node']] ?? INF)) {
                    $distances[$edge['node']] = $distance;
                    $towardsMouth[$edge['node']] = ['node' => $node, 'link' => $edge['link']];
                    $queue->insert($edge['node'], -$distance);
                }
            }
        }

        $node = array_keys($distances, max($distances))[0];
        $stem = [$positions[$node]];

        while (isset($towardsMouth[$node])) {
            $step = $towardsMouth[$node];
            $link = $links[$step['link']];
            $points = $link['start'] === $node ? $link['points'] : array_reverse($link['points']);
            $stem = [...$stem, ...array_slice($points, 1)];
            $node = $step['node'];
        }

        return $stem;
    }

    /**
     * Follows start/end node ids to build contiguous runs of links. Where
     * the river splits (an island, or a mill cut carrying the river's name)
     * the branch with the longest path downstream is taken as the main
     * stem, so a dead-end side channel never truncates the run. The runs
     * are then handed to the proximity chainer to bridge any gaps where the
     * main stem briefly carries a different name.
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

        $downstream = [];

        $longestDownstream = function (string $id) use (&$longestDownstream, &$downstream, $links, $byStart): float {
            if (isset($downstream[$id])) {
                return $downstream[$id];
            }

            $downstream[$id] = 0.0;
            $longest = 0.0;

            foreach ($byStart[$links[$id]['end']] ?? [] as $nextId) {
                $longest = max($longest, $longestDownstream($nextId));
            }

            return $downstream[$id] = $this->linkLength($links[$id]['points']) + $longest;
        };

        foreach (array_keys($links) as $id) {
            $longestDownstream($id);
        }

        $endNodes = array_flip(array_column($links, 'end'));
        $sources = array_keys(array_filter($links, fn (array $link) => ! isset($endNodes[$link['start']])));
        usort($sources, fn (string $a, string $b) => $downstream[$b] <=> $downstream[$a]);

        $visited = [];
        $runs = [];

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
                usort($nextIds, fn (string $a, string $b) => $downstream[$b] <=> $downstream[$a]);

                $currentId = $nextIds[0] ?? null;
            }

            if ($run !== []) {
                $runs[] = $run;
            }
        }

        return $runs;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    private function linkLength(array $points): float
    {
        $length = 0.0;

        for ($i = 1, $count = count($points); $i < $count; $i++) {
            $length += $this->distance($points[$i - 1], $points[$i]);
        }

        return $length;
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
