<?php

namespace App\Data;

use Illuminate\Support\Carbon;

final readonly class GaugeReadingData
{
    public function __construct(
        public Carbon $measuredAt,
        public float $value,
    ) {}

    /**
     * @param  array<string, mixed>  $item  A reading item from the EA flood monitoring API
     */
    public static function fromEnvironmentAgency(array $item): self
    {
        $value = $item['value'];

        return new self(
            measuredAt: Carbon::parse($item['dateTime'])->utc(),
            value: (float) (is_array($value) ? reset($value) : $value),
        );
    }
}
