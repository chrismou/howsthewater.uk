<?php

namespace App\Data;

use App\Enums\Agency;
use Illuminate\Support\Carbon;

final readonly class GaugeStationData
{
    /** NRW gauges are stored as "NRW" followed by NRW's location number. */
    public const NRW_PREFIX = 'NRW';

    public function __construct(
        public string $externalId,
        public string $label,
        public ?string $riverName,
        public ?string $town,
        public float $latitude,
        public float $longitude,
        public ?string $measureId,
        public ?string $unit,
        public ?float $typicalRangeLow,
        public ?float $typicalRangeHigh,
        public bool $isActive,
        public Agency $network = Agency::EnvironmentAgency,
        public ?string $rloiId = null,
    ) {
    }

    /**
     * @param  array{station_name: string, station_no: string, station_latitude: string, station_longitude: string, river_name: ?string, ts_id: string, ts_unitsymbol: ?string, to: ?string}  $series  A station's 15 minute stage series from SEPA's time series API, with its river
     */
    public static function fromSepa(array $series): self
    {
        return new self(
            externalId: 'SEPA'.$series['station_no'],
            label: $series['station_name'],
            riverName: $series['river_name'] === '' ? null : $series['river_name'],
            town: null,
            latitude: (float) $series['station_latitude'],
            longitude: (float) $series['station_longitude'],
            measureId: $series['ts_id'],
            unit: $series['ts_unitsymbol'] === null || $series['ts_unitsymbol'] === '' ? 'm' : $series['ts_unitsymbol'],
            typicalRangeLow: null,
            typicalRangeHigh: null,
            isActive: self::reportedRecently($series['to']),
            network: Agency::Sepa,
        );
    }

    /**
     * SEPA and NRW keep closed gauges in their lists, so a series with no reading in the last 30 days counts as inactive.
     */
    public static function reportedRecently(?string $lastReadingAt): bool
    {
        return $lastReadingAt !== null && $lastReadingAt !== '' && Carbon::parse($lastReadingAt)->isAfter(now()->subDays(30));
    }

    /**
     * NRW titles each gauge "<river> at <place>" ("Wye at Glasbury"), naming
     * the river without "River" or "Afon".
     *
     * @param  array<string, mixed>  $station  A station from NRW's station data
     * @param  array<array-key, mixed>  $parameter  Its river level parameter
     */
    public static function fromNaturalResourcesWales(array $station, array $parameter): self
    {
        $title = is_string($station['titleEn'] ?? null) ? $station['titleEn'] : '';
        $riverName = preg_match('/^(.+?)\s+at\s+/i', $title, $matches) === 1 ? trim($matches[1]) : null;
        $unit = is_string($parameter['units'] ?? null) && $parameter['units'] !== '' ? $parameter['units'] : 'm';

        return new self(
            externalId: self::NRW_PREFIX.$station['location'],
            label: is_string($station['nameEN'] ?? null) && $station['nameEN'] !== '' ? $station['nameEN'] : $title,
            riverName: $riverName,
            town: null,
            latitude: (float) $station['latitude'],
            longitude: (float) $station['longitude'],
            measureId: (string) $parameter['parameter'],
            unit: $unit,
            typicalRangeLow: null,
            typicalRangeHigh: null,
            isActive: ($station['statusEN'] ?? null) === 'Online' && self::reportedRecently(is_string($parameter['latestTime'] ?? null) ? $parameter['latestTime'] : null),
            network: Agency::NaturalResourcesWales,
        );
    }

    /**
     * @param  array<string, mixed>  $item  A station item from the EA flood monitoring API
     */
    public static function fromEnvironmentAgency(array $item): self
    {
        $measure = self::levelMeasure($item['measures'] ?? []);

        return new self(
            externalId: (string) ($item['stationReference'] ?? $item['notation']),
            label: self::firstString($item['label'] ?? 'Unknown'),
            riverName: self::firstStringOrNull($item['riverName'] ?? null),
            town: self::firstStringOrNull($item['town'] ?? null),
            latitude: (float) self::firstScalar($item['lat']),
            longitude: (float) self::firstScalar($item['long']),
            measureId: $measure['id'] ?? null,
            unit: $measure['unit'] ?? null,
            typicalRangeLow: isset($item['stageScale']['typicalRangeLow']) ? (float) $item['stageScale']['typicalRangeLow'] : null,
            typicalRangeHigh: isset($item['stageScale']['typicalRangeHigh']) ? (float) $item['stageScale']['typicalRangeHigh'] : null,
            isActive: str_ends_with((string) ($item['status'] ?? ''), 'statusActive'),
            rloiId: self::firstStringOrNull($item['RLOIid'] ?? null),
        );
    }

    /**
     * Picks the 15-minute stage level measure for the station. Some stations
     * list a duplicate measure with no unit that never carries readings, so
     * a measure with a real unit wins, and a stage in metres above the gauge
     * datum beats one in metres above sea level (mAOD).
     *
     * @param  array<int, array<string, mixed>>|array<string, mixed>|string  $measures
     * @return array{id: string, unit: string|null}|null
     */
    private static function levelMeasure(array|string $measures): ?array
    {
        if (is_string($measures)) {
            return null;
        }

        if (isset($measures['@id'])) {
            $measures = [$measures];
        }

        $candidates = [];

        foreach ($measures as $measure) {
            if (! is_array($measure) || ! isset($measure['@id'])) {
                continue;
            }

            $isLevel = ($measure['parameter'] ?? null) === 'level';
            $isStage = strcasecmp((string) ($measure['qualifier'] ?? ''), 'Stage') === 0;

            if ($isLevel && $isStage) {
                $unit = (string) ($measure['unitName'] ?? '');
                $hasUnit = ! in_array($unit, ['---', ''], true);
                $candidates[] = [
                    'id' => basename((string) $measure['@id']),
                    'unit' => $hasUnit ? $unit : null,
                    'rank' => [$hasUnit, strcasecmp($unit, 'mAOD') !== 0],
                ];
            }
        }

        usort($candidates, fn (array $a, array $b) => $b['rank'] <=> $a['rank']);

        return isset($candidates[0]) ? ['id' => $candidates[0]['id'], 'unit' => $candidates[0]['unit']] : null;
    }

    private static function firstScalar(mixed $value): mixed
    {
        return is_array($value) ? reset($value) : $value;
    }

    private static function firstString(mixed $value): string
    {
        return (string) self::firstScalar($value);
    }

    private static function firstStringOrNull(mixed $value): ?string
    {
        $scalar = self::firstScalar($value);

        return $scalar === null || $scalar === '' ? null : (string) $scalar;
    }
}
