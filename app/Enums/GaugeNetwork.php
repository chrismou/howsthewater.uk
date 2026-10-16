<?php

namespace App\Enums;

/**
 * Who runs a river level or rain gauge, which decides the API its readings come from.
 */
enum GaugeNetwork: string
{
    case EnvironmentAgency = 'environment_agency';
    case Sepa = 'sepa';
}
