<?php

namespace App\Data;

use App\Enums\Agency;

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
     * The watercourses the area names, e.g. "River Wharfe, Kel Beck" as two.
     *
     * @return array<int, string>
     */
    public function watercourses(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->riverOrSea ?? ''))));
    }
}
