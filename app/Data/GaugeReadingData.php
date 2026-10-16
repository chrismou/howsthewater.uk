<?php

namespace App\Data;

use Illuminate\Support\Carbon;

final readonly class GaugeReadingData
{
    public function __construct(
        public Carbon $measuredAt,
        public float $value,
    ) {
    }

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

    /**
     * @param  array{0: string, 1: int|float|string}  $row  A [timestamp, value] row from SEPA's time series API
     */
    public static function fromSepa(array $row): self
    {
        return new self(
            measuredAt: Carbon::parse($row[0])->utc(),
            value: (float) $row[1],
        );
    }
}
