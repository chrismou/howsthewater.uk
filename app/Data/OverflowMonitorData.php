<?php

namespace App\Data;

use App\Enums\OverflowStatus;
use Illuminate\Support\Carbon;

final readonly class OverflowMonitorData
{
    public function __construct(
        public string $externalId,
        public string $company,
        public ?string $receivingWatercourse,
        public float $latitude,
        public float $longitude,
        public OverflowStatus $status,
        public ?Carbon $statusStartedAt,
        public ?Carbon $latestEventStartedAt,
        public ?Carbon $latestEventEndedAt,
        public ?Carbon $sourceUpdatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  A feature's attributes from the ArcGIS feed
     */
    public static function fromArcGis(array $attributes): self
    {
        return new self(
            externalId: (string) $attributes['Id'],
            company: (string) ($attributes['Company'] ?? 'Unknown'),
            receivingWatercourse: self::cleanWatercourse($attributes['ReceivingWaterCourse'] ?? null),
            latitude: (float) $attributes['Latitude'],
            longitude: (float) $attributes['Longitude'],
            status: OverflowStatus::tryFrom((int) ($attributes['Status'] ?? 0)) ?? OverflowStatus::Offline,
            statusStartedAt: self::fromEpochMillis($attributes['StatusStart'] ?? null),
            latestEventStartedAt: self::fromEpochMillis($attributes['LatestEventStart'] ?? null),
            latestEventEndedAt: self::fromEpochMillis($attributes['LatestEventEnd'] ?? null),
            sourceUpdatedAt: self::fromEpochMillis($attributes['LastUpdated'] ?? null),
        );
    }

    private static function fromEpochMillis(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::createFromTimestampMs((int) $value, 'UTC');
    }

    private static function cleanWatercourse(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' || $trimmed === '#N/A' ? null : $trimmed;
    }
}
