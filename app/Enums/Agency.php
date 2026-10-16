<?php

namespace App\Enums;

/**
 * The agency publishing a gauge or bathing water, which decides the API its data comes from.
 */
enum Agency: string
{
    case EnvironmentAgency = 'environment_agency';
    case Sepa = 'sepa';

    public function label(): string
    {
        return match ($this) {
            self::EnvironmentAgency => 'Environment Agency',
            self::Sepa => 'SEPA',
        };
    }
}
