<?php

namespace App\Data;

use Carbon\Exceptions\InvalidFormatException;
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

    /**
     * A row of a SEPA results page: a date such as "18 May 2026" and counts
     * such as "<10" or "620". SEPA gives no time, so the sample is placed at
     * the start of that UK day.
     */
    public static function fromSepa(string $date, string $eColi, string $enterococci): ?self
    {
        try {
            $sampledAt = Carbon::createFromFormat('!d F Y', trim($date), self::SOURCE_TIMEZONE);
        } catch (InvalidFormatException) {
            return null;
        }

        if ($sampledAt === null) {
            return null;
        }

        [$eColiCount, $eColiQualifier] = self::sepaCount($eColi);
        [$enterococciCount, $enterococciQualifier] = self::sepaCount($enterococci);

        return new self(
            sampledAt: $sampledAt->utc(),
            eColi: $eColiCount,
            eColiQualifier: $eColiQualifier,
            enterococci: $enterococciCount,
            enterococciQualifier: $enterococciQualifier,
        );
    }

    /**
     * @return array{0: int|null, 1: string|null}
     */
    private static function sepaCount(string $text): array
    {
        if (preg_match('/^\s*([<>]?)\s*([\d,]+)\s*$/', $text, $match) !== 1) {
            return [null, null];
        }

        return [(int) str_replace(',', '', $match[2]), $match[1] === '' ? null : $match[1]];
    }

    private static function qualifier(mixed $value): ?string
    {
        $notation = is_array($value) ? ($value['countQualifierNotation'] ?? null) : null;

        return is_string($notation) && $notation !== '' ? $notation : null;
    }
}
