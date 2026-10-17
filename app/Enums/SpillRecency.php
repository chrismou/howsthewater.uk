<?php

namespace App\Enums;

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
            self::None => 'No spills reported in the last 48 hours',
            self::Offline => 'Upstream monitors offline, no spill data',
            self::NoData => 'No monitored overflows upstream',
            self::NotCovered => 'Overflow data not available here yet',
        };
    }
}
