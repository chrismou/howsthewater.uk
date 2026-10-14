<?php

namespace App\Data;

use App\Enums\FloodSeverity;
use App\Models\FloodWarning;
use Illuminate\Support\Carbon;

/**
 * A flood alert or warning in force for an area covering a spot, as plain
 * values so it can sit in both SpotConditions and SpotSummary.
 */
final readonly class FloodWarningSummary
{
    /**
     * A warning not seen by a poll for this long may have been lifted since.
     */
    public const STALE_AFTER_HOURS = 6;

    public function __construct(
        public string $areaCode,
        public string $areaLabel,
        public ?string $areaDescription,
        public FloodSeverity $severity,
        public ?string $message,
        public ?Carbon $raisedAt,
        public ?Carbon $messageChangedAt,
        public Carbon $checkedAt,
    ) {
    }

    public static function fromWarning(FloodWarning $warning): self
    {
        return new self(
            areaCode: $warning->floodArea->code,
            areaLabel: $warning->floodArea->label,
            areaDescription: $warning->floodArea->description,
            severity: $warning->severity_level,
            message: $warning->message,
            raisedAt: $warning->raised_at,
            messageChangedAt: $warning->message_changed_at,
            checkedAt: $warning->checked_at,
        );
    }

    /**
     * The area's page on GOV.UK's Check for flooding service.
     */
    public function url(): string
    {
        return 'https://check-for-flooding.service.gov.uk/target-area/'.rawurlencode($this->areaCode);
    }

    /**
     * Whether the warning has gone unconfirmed too long to rely on. Evaluated
     * at read time, so a cached result still goes stale on schedule.
     */
    public function isStale(): bool
    {
        return $this->checkedAt->lessThan(now()->subHours(self::STALE_AFTER_HOURS));
    }
}
