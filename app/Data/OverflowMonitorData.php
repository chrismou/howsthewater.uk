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
     * Stream feeds share their fields, but not always their case: South West Water's are camelCase ("receivingWaterCourse"),
     * so names are matched case-insensitively.
     *
     * @param  array<string, mixed>  $attributes  A feature's attributes from the ArcGIS feed
     */
    public static function fromArcGis(array $attributes): self
    {
        $attributes = array_change_key_case($attributes, CASE_LOWER);

        return new self(
            externalId: (string) $attributes['id'],
            company: (string) ($attributes['company'] ?? 'Unknown'),
            receivingWatercourse: self::cleanWatercourse($attributes['receivingwatercourse'] ?? null),
            latitude: (float) $attributes['latitude'],
            longitude: (float) $attributes['longitude'],
            status: OverflowStatus::tryFrom((int) ($attributes['status'] ?? 0)) ?? OverflowStatus::Offline,
            statusStartedAt: self::fromEpochMillis($attributes['statusstart'] ?? null),
            latestEventStartedAt: self::fromEpochMillis($attributes['latesteventstart'] ?? null),
            latestEventEndedAt: self::fromEpochMillis($attributes['latesteventend'] ?? null),
            sourceUpdatedAt: self::fromEpochMillis($attributes['lastupdated'] ?? null),
        );
    }

    /**
     * Dŵr Cymru Welsh Water's storm overflow map gives each overflow's status in words ("Overflow Operating", "Overflow
     * Not Operating", "Overflow Not Operating (Has in the last 24 hours)", "Under Investigation") and its latest
     * discharge as UK local times without an offset. Its ids already carry a company prefix (DCWW1003). Each record's
     * EditDate only moves when it changes, so the feed is marked updated_on_change.
     *
     * @param  array<string, mixed>  $attributes  A feature's attributes from the map's feature service
     * @param  array{x?: mixed, y?: mixed}  $geometry  The feature's point in WGS84
     */
    public static function fromWelshWater(array $attributes, array $geometry): self
    {
        $description = (string) ($attributes['status'] ?? '');
        $status = match (true) {
            $description === 'Overflow Operating' => OverflowStatus::Discharging,
            str_starts_with($description, 'Overflow Not Operating') => OverflowStatus::NotDischarging,
            default => OverflowStatus::Offline,
        };
        $latestEventStartedAt = self::fromLocalTime($attributes['start_date_time_discharge'] ?? null);
        $latestEventEndedAt = self::fromLocalTime($attributes['stop_date_time_discharge'] ?? null);

        return new self(
            externalId: (string) $attributes['DCWW_ID'],
            company: 'Dŵr Cymru Welsh Water',
            receivingWatercourse: self::cleanWatercourse($attributes['Receiving_Water'] ?? null),
            latitude: (float) ($geometry['y'] ?? 0),
            longitude: (float) ($geometry['x'] ?? 0),
            status: $status,
            statusStartedAt: match ($status) {
                OverflowStatus::Discharging => $latestEventStartedAt,
                OverflowStatus::NotDischarging => $latestEventEndedAt,
                OverflowStatus::Offline => null,
            },
            latestEventStartedAt: $latestEventStartedAt,
            latestEventEndedAt: $status === OverflowStatus::Discharging ? null : $latestEventEndedAt,
            sourceUpdatedAt: self::fromEpochMillis($attributes['EditDate'] ?? null),
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

    private static function fromLocalTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value, 'Europe/London')->utc();
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
