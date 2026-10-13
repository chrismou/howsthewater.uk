<?php

namespace App\Enums;

enum OverflowStatus: int
{
    case Offline = -1;
    case NotDischarging = 0;
    case Discharging = 1;

    public function label(): string
    {
        return match ($this) {
            self::Offline => 'Monitor offline',
            self::NotDischarging => 'Not discharging',
            self::Discharging => 'Discharging now',
        };
    }
}
