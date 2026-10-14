<?php

namespace App\Data;

use Illuminate\Support\Carbon;

final readonly class BathingWaterSampleData
{
    /**
     * The EA publishes sample times in UK local time without an offset.
     */
    private const SOURCE_TIMEZONE = 'Europe/London';

    public function __construct(
        public Carbon $sampledAt,
        public ?int $eColi,
        public ?string $eColiQualifier,
        public ?int $enterococci,
        public ?string $enterococciQualifier,
    ) {
    }

    /**
     * @param  array<string, mixed>  $item  A sample assessment from the EA bathing water quality API
     */
    public static function fromEnvironmentAgency(array $item): ?self
    {
        $time = $item['sampleDateTime'] ?? null;
        $time = is_array($time) ? ($time['inXSDDateTime']['_value'] ?? $time['_about'] ?? null) : $time;

        if (! is_string($time) || $time === '') {
            return null;
        }

        return new self(
            sampledAt: Carbon::parse(basename($time), self::SOURCE_TIMEZONE)->utc(),
            eColi: isset($item['escherichiaColiCount']) ? (int) $item['escherichiaColiCount'] : null,
            eColiQualifier: self::qualifier($item['escherichiaColiQualifier'] ?? null),
            enterococci: isset($item['intestinalEnterococciCount']) ? (int) $item['intestinalEnterococciCount'] : null,
            enterococciQualifier: self::qualifier($item['intestinalEnterococciQualifier'] ?? null),
        );
    }

    private static function qualifier(mixed $value): ?string
    {
        $notation = is_array($value) ? ($value['countQualifierNotation'] ?? null) : null;

        return is_string($notation) && $notation !== '' ? $notation : null;
    }
}
