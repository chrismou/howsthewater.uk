<?php

namespace App\Enums;

/**
 * The Environment Agency's daily pollution risk forecast, published in the
 * bathing season for sites where rain or tide can lower water quality.
 */
enum PollutionRisk: string
{
    case Normal = 'normal';
    case Increased = 'increased';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal pollution risk forecast',
            self::Increased => 'Increased pollution risk forecast',
        };
    }
}
