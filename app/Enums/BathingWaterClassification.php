<?php

namespace App\Enums;

/**
 * The annual classification each designated bathing water receives, based
 * on the previous four seasons of samples. It describes those samples, not
 * the water on any given day.
 */
enum BathingWaterClassification: string
{
    case Excellent = 'Excellent';
    case Good = 'Good';
    case Sufficient = 'Sufficient';
    case Poor = 'Poor';

    public function meaning(): string
    {
        return match ($this) {
            self::Excellent => 'The cleanest samples over the last four seasons.',
            self::Good => 'Generally good samples over the last four seasons.',
            self::Sufficient => 'Samples met the minimum standard over the last four seasons.',
            self::Poor => 'Samples failed the minimum standard, and the advice is against bathing.',
        };
    }
}
