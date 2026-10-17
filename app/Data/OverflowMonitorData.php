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
    ) {
    }

    public function withSourceUpdatedAt(Carbon $sourceUpdatedAt): self
    {
        return new self(
            externalId: $this->externalId,
            company: $this->company,
            receivingWatercourse: $this->receivingWatercourse,
            latitude: $this->latitude,
            longitude: $this->longitude,
            status: $this->status,
            statusStartedAt: $this->statusStartedAt,
            latestEventStartedAt: $this->latestEventStartedAt,
            latestEventEndedAt: $this->latestEventEndedAt,
            sourceUpdatedAt: $sourceUpdatedAt,
        );
    }

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

    /**
     * Scottish Water reports each monitor's latest overflow and a status code:
     * OF overflowing, RO a recent overflow, NO none and DA no data. Its asset
     * ids carry no company prefix, so one is added to keep them apart from
     * the English feeds. Feed freshness comes from the response, as each
     * monitor transmits on its own schedule.
     *
     * @param  array<string, mixed>  $record  One result from the near real-time API
     */
    public static function fromScottishWater(array $record, ?Carbon $feedUpdatedAt): self
    {
        $status = match (substr((string) ($record['OVERFLOW_STATUS_DESCRIPTION'] ?? ''), 0, 2)) {
            'OF' => OverflowStatus::Discharging,
            'RO', 'NO' => OverflowStatus::NotDischarging,
            default => OverflowStatus::Offline,
        };
        $latestEventStartedAt = self::fromIsoString($record['OVERFLOW_START_DATETIME'] ?? null);
        $latestEventEndedAt = self::fromIsoString($record['OVERFLOW_END_DATETIME'] ?? null);

        return new self(
            externalId: 'SW'.$record['ASSET_ID'],
            company: 'Scottish Water',
            receivingWatercourse: self::cleanWatercourse($record['RECEIVING_WATER'] ?? null),
            latitude: (float) $record['DISCHARGE_OVERFLOW_LOCATION_LATITUDE'],
            longitude: (float) $record['DISCHARGE_OVERFLOW_LOCATION_LONGITUDE'],
            status: $status,
            statusStartedAt: match ($status) {
                OverflowStatus::Discharging => $latestEventStartedAt,
                OverflowStatus::NotDischarging => $latestEventEndedAt,
                OverflowStatus::Offline => self::fromIsoString($record['LAST_TRANSMITTED_DATETIME'] ?? null),
            },
            latestEventStartedAt: $latestEventStartedAt,
            latestEventEndedAt: $latestEventEndedAt,
            sourceUpdatedAt: $feedUpdatedAt,
        );
    }

    private static function fromIsoString(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value)->utc();
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

        return $trimmed === '' || $trimmed === '#N/A' || strcasecmp($trimmed, 'Un-named watercourse') === 0 ? null : $trimmed;
    }
}
