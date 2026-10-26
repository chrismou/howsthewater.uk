<?php

namespace App\Data;

use App\Enums\Agency;
use RuntimeException;

final readonly class FloodAreaData
{
    public function __construct(
        public string $code,
        public string $label,
        public ?string $description,
        public ?string $riverOrSea,
        public Agency $agency = Agency::EnvironmentAgency,
    ) {
    }

    /**
     * @param  array<string, mixed>  $item  A flood area item from the EA flood monitoring API
     */
    public static function fromEnvironmentAgency(array $item): self
    {
        return new self(
            code: (string) ($item['fwdCode'] ?? $item['notation']),
            label: (string) ($item['label'] ?? $item['notation']),
            description: isset($item['description']) && $item['description'] !== '' ? (string) $item['description'] : null,
            riverOrSea: isset($item['riverOrSea']) && $item['riverOrSea'] !== '' ? (string) $item['riverOrSea'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $properties  A flood warning or alert area from NRW's DataMapWales layers
     */
    public static function fromNaturalResourcesWales(array $properties): self
    {
        $text = fn (mixed $value) => is_string($value) && trim($value) !== '' ? trim($value) : null;
        $code = (string) $properties['fwd_tacode'];

        return new self(
            code: $code,
            label: $text($properties['fwa_name'] ?? null) ?? $code,
            description: $text($properties['descrip'] ?? null),
            riverOrSea: $text($properties['river_sea'] ?? null),
            agency: Agency::NaturalResourcesWales,
        );
    }

    /**
     * SEPA's alert areas are regions (Tayside) and its warning areas are named by place
     * (Riverside and Cambuskenneth), with the river in the scheme's name at best, so
     * the description says what kind of area it is rather than which river.
     *
     * @param  array<string, mixed>  $attributes  A feature's attributes from one of the Floodline map's layers
     */
    public static function fromSepa(array $attributes, bool $isAlertArea): self
    {
        $text = fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null;
        $code = self::sepaCode($attributes, $isAlertArea);

        if ($isAlertArea) {
            return new self(code: $code, label: $text($attributes['name'] ?? null) ?? $code, description: "SEPA's regional flood alert area", riverOrSea: null, agency: Agency::Sepa);
        }

        $source = match (strtolower((string) ($attributes['source_of_flood'] ?? ''))) {
            'fluvial' => 'river flooding',
            'coastal' => 'coastal flooding',
            'coastal/fluvial', 'fluvial/coastal' => 'coastal and river flooding',
            default => null,
        };
        $authority = $text($attributes['local_authority'] ?? null);

        return new self(
            code: $code,
            label: $text($attributes['fwa_name'] ?? null) ?? $code,
            description: 'SEPA flood warning area'.($source !== null ? " for {$source}" : '').($authority !== null ? " in {$authority}" : ''),
            riverOrSea: $text($attributes['scheme'] ?? null),
            agency: Agency::Sepa,
        );
    }

    /**
     * Our code for a SEPA area, from Floodline's own id, prefixed by kind (alert areas
     * SEPA-A-, warning areas SEPA-W-) so the two layers' ids can never meet each other
     * or an Environment Agency or NRW code. A row without an id stops the read.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function sepaCode(array $attributes, bool $isAlertArea): string
    {
        $id = $attributes[$isAlertArea ? 'id' : 'map_id'] ?? null;

        if (! is_numeric($id) || (float) $id !== (float) (int) $id) {
            $name = $attributes[$isAlertArea ? 'name' : 'fwa_name'] ?? null;

            throw new RuntimeException(sprintf(
                'SEPA flood %s area "%s" has no whole-number id',
                $isAlertArea ? 'alert' : 'warning',
                is_scalar($name) ? $name : '?',
            ));
        }

        return ($isAlertArea ? 'SEPA-A-' : 'SEPA-W-').(int) $id;
    }

    /**
     * The watercourses the area names, e.g. "River Wharfe, Kel Beck" as two.
     *
     * @return array<int, string>
     */
    public function watercourses(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->riverOrSea ?? ''))));
    }
}
