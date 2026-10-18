<?php

namespace App\Enums;

/**
 * Where a company's published spill log was read from: the Environment Agency's start/stop files or a Stream log
 * service.
 */
enum SpillLogSource: string
{
    case EnvironmentAgency = 'environment_agency';
    case Stream = 'stream';

    /**
     * The words after "via" in the pages' notes.
     */
    public function viaLabel(): string
    {
        return match ($this) {
            self::EnvironmentAgency => 'the Environment Agency',
            self::Stream => 'Stream',
        };
    }
}
