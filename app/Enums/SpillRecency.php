<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * Summarises how recently any upstream overflow discharged. This is a
 * description of the data, deliberately not a safety rating.
 */
enum SpillRecency: string
{
    case DischargingNow = 'discharging_now';
    case Within24Hours = 'within_24_hours';
    case Within48Hours = 'within_48_hours';
    case None = 'none';
    case Offline = 'offline';
    case NoData = 'no_data';
    case NotCovered = 'not_covered';

    /**
     * The recency for a set of monitors. Every monitor being offline is an
     * absence of data, not a clean bill, so it never reads as "none". A
     * place whose water company feed we do not read yet is not covered,
     * which never reads as "no monitored overflows" either.
     */
    public static function assess(int $monitorCount, int $dischargingNow, int $offline, bool $spilledWithin24Hours, bool $spilledWithin48Hours, bool $covered = true): self
    {
        return match (true) {
            ! $covered => self::NotCovered,
            $monitorCount === 0 => self::NoData,
            $dischargingNow > 0 => self::DischargingNow,
            $spilledWithin24Hours => self::Within24Hours,
            $spilledWithin48Hours => self::Within48Hours,
            $offline === $monitorCount => self::Offline,
            default => self::None,
        };
    }

    /**
     * One overflow's own recency, on the same scale as a place's, so maps colour overflows and places alike. A monitor
     * that has not reported for SpotConditions::STALE_AFTER_HOURS gives no data, as its status is only what it was then.
     * latestSpillEnd: when its latest spill ended, or now while one is open.
     */
    public static function forMonitor(OverflowStatus $status, ?CarbonInterface $latestSpillEnd, bool $stale): self
    {
        if ($stale) {
            return self::NoData;
        }

        $spilledWithin = fn (int $hours) => $status === OverflowStatus::Discharging
            || ($latestSpillEnd?->greaterThanOrEqualTo(now()->subHours($hours)) ?? false);

        return self::assess(1, $status === OverflowStatus::Discharging ? 1 : 0, $status === OverflowStatus::Offline ? 1 : 0, $spilledWithin(24), $spilledWithin(48));
    }

    /**
     * The value a map marker takes: out of date overflow data shows as no data, never as the status it last had.
     */
    public function onMap(bool $stale = false): string
    {
        return $stale ? self::NoData->value : $this->value;
    }

    /**
     * The pill colour it shows in: red while discharging, amber after a recent spill, green when none was reported and
     * grey when there is no data to go on.
     */
    public function tone(): string
    {
        return match ($this) {
            self::DischargingNow => 'red',
            self::Within24Hours, self::Within48Hours => 'amber',
            self::None => 'green',
            self::Offline, self::NoData, self::NotCovered => 'grey',
        };
    }

    /**
     * The same summary for a beach or lake, where monitors are near the
     * water rather than upstream of it.
     */
    public function nearbyLabel(): string
    {
        return match ($this) {
            self::DischargingNow => 'Overflow discharging nearby now',
            self::Within24Hours => 'Spill nearby in the last 24 hours',
            self::Within48Hours => 'Spill nearby in the last 48 hours',
            self::None => 'No spills reported nearby in the last 48 hours',
            self::Offline => 'Nearby monitors offline, no spill data',
            self::NoData => 'No monitored overflows nearby',
            self::NotCovered => 'Overflow data not available here yet',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::DischargingNow => 'Discharging upstream now',
            self::Within24Hours => 'Spill upstream in the last 24 hours',
            self::Within48Hours => 'Spill upstream in the last 48 hours',
            self::None => 'No upstream spills reported in 48h',
            self::Offline => 'Upstream monitors offline, no spill data',
            self::NoData => 'No monitored overflows upstream',
            self::NotCovered => 'Overflow data not available here yet',
        };
    }
}
