<?php

namespace App\Enums;

enum LevelTrend: string
{
    case Rising = 'rising';
    case Steady = 'steady';
    case Falling = 'falling';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Rising => 'Rising',
            self::Steady => 'Steady',
            self::Falling => 'Falling',
            self::Unknown => 'Trend unknown',
        };
    }
}
