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

    public function label(): string
    {
        return match ($this) {
            self::DischargingNow => 'Discharging upstream now',
            self::Within24Hours => 'Spill upstream in the last 24 hours',
            self::Within48Hours => 'Spill upstream in the last 48 hours',
            self::None => 'No spills reported in the last 48 hours',
            self::Offline => 'Upstream monitors offline, no spill data',
            self::NoData => 'No monitored overflows upstream',
        };
    }
}
