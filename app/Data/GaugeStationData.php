<?php

namespace App\Data;

final readonly class GaugeStationData
{
    public function __construct(
        public string $externalId,
        public string $label,
        public ?string $riverName,
        public ?string $town,
        public float $latitude,
        public float $longitude,
        public ?string $measureId,
        public ?float $typicalRangeLow,
        public ?float $typicalRangeHigh,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $item  A station item from the EA flood monitoring API
     */
    public static function fromEnvironmentAgency(array $item): self
    {
        return new self(
            externalId: (string) ($item['stationReference'] ?? $item['notation']),
            label: self::firstString($item['label'] ?? 'Unknown'),
            riverName: self::firstStringOrNull($item['riverName'] ?? null),
            town: self::firstStringOrNull($item['town'] ?? null),
            latitude: (float) self::firstScalar($item['lat']),
            longitude: (float) self::firstScalar($item['long']),
            measureId: self::levelMeasureId($item['measures'] ?? []),
            typicalRangeLow: isset($item['stageScale']['typicalRangeLow']) ? (float) $item['stageScale']['typicalRangeLow'] : null,
            typicalRangeHigh: isset($item['stageScale']['typicalRangeHigh']) ? (float) $item['stageScale']['typicalRangeHigh'] : null,
            isActive: str_ends_with((string) ($item['status'] ?? ''), 'statusActive'),
        );
    }

    /**
     * Picks the 15-minute stage level measure for the station. Some stations
     * list a duplicate measure with no unit that never carries readings, so
     * a measure with a real unit wins.
     *
     * @param  array<int, array<string, mixed>>|array<string, mixed>|string  $measures
     */
    private static function levelMeasureId(array|string $measures): ?string
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
                $hasUnit = ! in_array($measure['unitName'] ?? '---', ['---', ''], true);
                $candidates[] = ['id' => basename((string) $measure['@id']), 'hasUnit' => $hasUnit];
            }
        }

        usort($candidates, fn (array $a, array $b) => $b['hasUnit'] <=> $a['hasUnit']);

        return $candidates[0]['id'] ?? null;
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
