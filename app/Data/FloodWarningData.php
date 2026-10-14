<?php

namespace App\Data;

use App\Enums\FloodSeverity;
use Illuminate\Support\Carbon;

final readonly class FloodWarningData
{
    /**
     * The EA publishes these times in UK local time without an offset.
     */
    private const SOURCE_TIMEZONE = 'Europe/London';

    public function __construct(
        public string $floodAreaCode,
        public ?FloodSeverity $severity,
        public ?string $message,
        public ?Carbon $raisedAt,
        public ?Carbon $messageChangedAt,
    ) {
    }

    /**
     * @param  array<string, mixed>  $item  A flood item from the EA flood monitoring API
     */
    public static function fromEnvironmentAgency(array $item): self
    {
        $message = trim((string) ($item['message'] ?? ''));

        return new self(
            floodAreaCode: (string) ($item['floodAreaID'] ?? $item['floodArea']['notation']),
            severity: FloodSeverity::tryFrom((int) ($item['severityLevel'] ?? 0)),
            message: $message === '' ? null : $message,
            raisedAt: self::time($item['timeRaised'] ?? null),
            messageChangedAt: self::time($item['timeMessageChanged'] ?? null),
        );
    }

    /**
     * Whether the warning is still in force; "no longer in force" items
     * linger in the feed for a day after a warning is lifted.
     */
    public function isInForce(): bool
    {
        return $this->severity !== null;
    }

    private static function time(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value, self::SOURCE_TIMEZONE)->utc() : null;
    }
}
