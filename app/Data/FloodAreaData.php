<?php

namespace App\Data;

final readonly class FloodAreaData
{
    public function __construct(
        public string $code,
        public string $label,
        public ?string $description,
        public ?string $riverOrSea,
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
     * The watercourses the area names, e.g. "River Wharfe, Kel Beck" as two.
     *
     * @return array<int, string>
     */
    public function watercourses(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->riverOrSea ?? ''))));
    }
}
