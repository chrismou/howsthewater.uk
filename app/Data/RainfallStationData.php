<?php

namespace App\Data;

final readonly class RainfallStationData
{
    public function __construct(
        public string $externalId,
        public string $label,
        public ?string $gridReference,
        public float $latitude,
        public float $longitude,
        public ?string $measureId,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $item  A station item from the EA flood monitoring API
     */
    public static function fromEnvironmentAgency(array $item): self
    {
        return new self(
            externalId: (string) ($item['stationReference'] ?? $item['notation']),
            label: self::firstString($item['label'] ?? 'Rainfall station'),
            gridReference: self::firstStringOrNull($item['gridReference'] ?? null),
            latitude: (float) self::firstScalar($item['lat']),
            longitude: (float) self::firstScalar($item['long']),
            measureId: self::rainfallMeasure($item['measures'] ?? []),
            isActive: ! str_ends_with((string) ($item['status'] ?? ''), 'statusInactive'),
        );
    }

    /**
     * Picks the 15 minute tipping bucket total in millimetres. Rain gauges
     * rarely list more than one measure, but an hourly or unitless one is
     * skipped in favour of the 15 minute series the summaries are built on.
     *
     * @param  array<int, array<string, mixed>>|array<string, mixed>|string  $measures
     */
    private static function rainfallMeasure(array|string $measures): ?string
    {
        if (is_string($measures)) {
            return null;
        }

        if (isset($measures['@id'])) {
            $measures = [$measures];
        }

        foreach ($measures as $measure) {
            if (! is_array($measure) || ! isset($measure['@id'])) {
                continue;
            }

            $isRainfall = ($measure['parameter'] ?? null) === 'rainfall';
            $isQuarterHourly = (int) ($measure['period'] ?? 900) === 900;
            $isMillimetres = strcasecmp((string) ($measure['unitName'] ?? 'mm'), 'mm') === 0;

            if ($isRainfall && $isQuarterHourly && $isMillimetres) {
                return basename((string) $measure['@id']);
            }
        }

        return null;
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
