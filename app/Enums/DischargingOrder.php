<?php

namespace App\Enums;

/**
 * How the spills pages order overflows discharging now: the latest to start first, so each visit shows what is new,
 * or the longest running first.
 */
enum DischargingOrder: string
{
    case Newest = 'newest';
    case Longest = 'longest';

    public function label(): string
    {
        return match ($this) {
            self::Newest => 'Newest',
            self::Longest => 'Longest running',
        };
    }
}
