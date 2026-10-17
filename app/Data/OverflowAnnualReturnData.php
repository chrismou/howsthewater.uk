<?php

namespace App\Data;

/**
 * One row of an event duration monitoring annual return, whichever year's field names it came with.
 */
final readonly class OverflowAnnualReturnData
{
    public function __construct(
        public ?string $uid,
        public string $company,
        public ?string $siteName,
        public ?string $permitReference,
        public ?int $countedSpills,
        public ?float $totalHours,
        public ?float $monitorUptimePercent,
        public ?string $receivingWater,
        public ?float $latitude,
        public ?float $longitude,
    ) {
    }

    /**
     * Field names change case between years (SiteNameEA in 2021), so they are matched case-insensitively. Only an id
     * in the live feeds' form (YWS02215) is kept: 2021 numbers its rows instead, and Welsh rows carry none.
     *
     * @param  array<string, mixed>  $attributes  A feature's attributes from the Rivers Trust's annual return layer
     */
    public static function fromArcGis(array $attributes): self
    {
        $attributes = array_change_key_case($attributes, CASE_LOWER);
        $uid = is_string($attributes['uid'] ?? null) && preg_match('/^[A-Z]{2,5}\d+$/', trim($attributes['uid'])) === 1 ? trim($attributes['uid']) : null;

        return new self(
            uid: $uid,
            company: trim((string) ($attributes['watercompanyname'] ?? 'Unknown')),
            siteName: self::text($attributes['sitenameea'] ?? null) ?? self::text($attributes['sitenamewasc'] ?? null),
            permitReference: self::text($attributes['permitreferenceea'] ?? null),
            countedSpills: is_numeric($attributes['countedspills'] ?? null) ? (int) round((float) $attributes['countedspills']) : null,
            totalHours: is_numeric($attributes['totaldurationallspillshrs'] ?? null) ? (float) $attributes['totaldurationallspillshrs'] : null,
            monitorUptimePercent: is_numeric($attributes['edmoperationpercent'] ?? null) ? min(100.0, max(0.0, (float) $attributes['edmoperationpercent'])) : null,
            receivingWater: self::text($attributes['recievingwatername'] ?? null),
            latitude: is_numeric($attributes['latitude'] ?? null) ? (float) $attributes['latitude'] : null,
            longitude: is_numeric($attributes['longitude'] ?? null) ? (float) $attributes['longitude'] : null,
        );
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' || $trimmed === '#N/A' ? null : $trimmed;
    }
}
