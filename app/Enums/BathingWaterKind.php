<?php

namespace App\Enums;

/**
 * The Environment Agency's bathing water types. Coastal and estuary sites
 * are beaches; lake and river sites sit on our lakes and spots.
 */
enum BathingWaterKind: string
{
    case Coastal = 'coastal';
    case Estuary = 'estuary';
    case Lake = 'lake';
    case River = 'river';

    /**
     * @param  array<int, mixed>  $types  The site's EA type URIs
     */
    public static function fromTypes(array $types): ?self
    {
        $names = array_map(fn ($type) => basename((string) $type), $types);

        return match (true) {
            in_array('CoastalBathingWater', $names, true) => self::Coastal,
            in_array('TransitionalBathingWater', $names, true) => self::Estuary,
            in_array('LakeBathingWater', $names, true) => self::Lake,
            in_array('RiverBathingWater', $names, true) => self::River,
            default => null,
        };
    }

    public function isBeach(): bool
    {
        return $this === self::Coastal || $this === self::Estuary;
    }
}
