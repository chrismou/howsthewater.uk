<?php

namespace App\Enums;

/**
 * The Environment Agency's flood severity levels, most severe first. Level 4
 * ("warning no longer in force") is never stored, so it has no case.
 */
enum FloodSeverity: int
{
    case SevereFloodWarning = 1;
    case FloodWarning = 2;
    case FloodAlert = 3;

    public function label(): string
    {
        return match ($this) {
            self::SevereFloodWarning => 'Severe flood warning',
            self::FloodWarning => 'Flood warning',
            self::FloodAlert => 'Flood alert',
        };
    }

    /**
     * The EA's own one-line meaning of each level.
     */
    public function meaning(): string
    {
        return match ($this) {
            self::SevereFloodWarning => 'Severe flooding. Danger to life.',
            self::FloodWarning => 'Flooding is expected. Immediate action required.',
            self::FloodAlert => 'Flooding is possible. Be prepared.',
        };
    }
}
