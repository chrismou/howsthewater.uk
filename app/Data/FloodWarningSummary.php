<?php

namespace App\Data;

use App\Enums\Agency;
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
        public Agency $agency = Agency::EnvironmentAgency,
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
            agency: $warning->floodArea->agency,
        );
    }

    /**
     * The area's page on GOV.UK's Check for flooding service, or for a Welsh or
     * Scottish area the agency's live warnings page, as neither has an address per area.
     */
    public function url(): string
    {
        return match ($this->agency) {
            Agency::NaturalResourcesWales => 'https://flood-warning.naturalresources.wales/',
            Agency::Sepa => 'https://floodline.sepa.org.uk/live-flood-information/',
            Agency::EnvironmentAgency => 'https://check-for-flooding.service.gov.uk/target-area/'.rawurlencode($this->areaCode),
        };
    }

    /**
     * The link text for url().
     */
    public function urlLabel(): string
    {
        return match ($this->agency) {
            Agency::NaturalResourcesWales => 'Read the latest from Natural Resources Wales',
            Agency::Sepa => 'Read the latest on Floodline',
            Agency::EnvironmentAgency => 'Read the latest on GOV.UK',
        };
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
