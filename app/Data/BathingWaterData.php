<?php

namespace App\Data;

use App\Enums\Agency;
use App\Enums\BathingWaterClassification;
use App\Enums\BathingWaterKind;
use App\Enums\PollutionRisk;
use Illuminate\Support\Carbon;

/**
 * One designated bathing water, from the EA register or Wales's register
 * on the same platform (with its latest classification, sample and
 * pollution risk forecast) or from SEPA's map service (location and
 * classification only).
 */
final readonly class BathingWaterData
{
    /**
     * The EA publishes forecast times in UK local time without an offset.
     */
    private const SOURCE_TIMEZONE = 'Europe/London';

    /**
     * @param  array<int, string>  $districts
     * @param  string|null  $lakeName  The lake the site is on when its name does not start with it (SEPA's Luss Bay is on Loch Lomond)
     */
    public function __construct(
        public string $eubwid,
        public string $samplingPoint,
        public ?BathingWaterKind $kind,
        public string $name,
        public array $districts,
        public ?string $sewerageUndertaker,
        public float $latitude,
        public float $longitude,
        public ?string $description,
        public ?BathingWaterClassification $classification,
        public ?int $classificationYear,
        public ?PollutionRisk $riskLevel,
        public ?Carbon $riskExpiresAt,
        public bool $affectedByHeavyRain,
        public ?Carbon $seasonStartsOn,
        public ?Carbon $seasonEndsOn,
        public ?BathingWaterSampleData $latestSample,
        public Agency $agency = Agency::EnvironmentAgency,
        public ?string $profileUrl = null,
        public ?string $lakeName = null,
    ) {
    }

    /**
     * Scotland's bathing season runs from 1 June to 15 September.
     *
     * @param  array{bw_id: string, name: string, classification: ?string, year: ?int, url: string, latitude: float, longitude: float, district: string, lake: ?string}  $site  A site read from SEPA's bathing water map layers
     */
    public static function fromSepa(array $site): self
    {
        parse_str((string) parse_url($site['url'], PHP_URL_QUERY), $query);
        $location = $query['location'] ?? null;

        return new self(
            eubwid: $site['bw_id'],
            samplingPoint: is_string($location) && $location !== '' ? $location : $site['bw_id'],
            kind: $site['lake'] === null ? BathingWaterKind::Coastal : BathingWaterKind::Lake,
            name: $site['name'],
            districts: [$site['district']],
            sewerageUndertaker: 'Scottish Water',
            latitude: $site['latitude'],
            longitude: $site['longitude'],
            description: null,
            classification: BathingWaterClassification::tryFrom((string) $site['classification']),
            classificationYear: $site['year'],
            riskLevel: null,
            riskExpiresAt: null,
            affectedByHeavyRain: false,
            seasonStartsOn: now()->setDate(now()->year, 6, 1)->startOfDay(),
            seasonEndsOn: now()->setDate(now()->year, 9, 15)->startOfDay(),
            latestSample: null,
            agency: Agency::Sepa,
            profileUrl: $site['url'],
            lakeName: $site['lake'],
        );
    }

    /**
     * Wales's register (published for NRW) names sites by label rather than name.
     *
     * @param  array<string, mixed>  $item  A bathing water from the EA register, or Wales's on the same platform
     */
    public static function fromEnvironmentAgency(array $item, Agency $agency = Agency::EnvironmentAgency, ?string $profileUrl = null): self
    {
        $types = $item['type'] ?? [];
        $point = $item['samplingPoint'] ?? [];
        $compliance = is_array($item['latestComplianceAssessment'] ?? null) ? $item['latestComplianceAssessment'] : [];
        $risk = is_array($item['latestRiskPrediction'] ?? null) ? $item['latestRiskPrediction'] : [];
        $profile = is_array($item['latestProfile'] ?? null) ? $item['latestProfile'] : [];
        $sample = is_array($item['latestSampleAssessment'] ?? null) ? $item['latestSampleAssessment'] : null;
        $expires = self::text($risk['expiresAt'] ?? null);
        $heavyRain = $item['waterQualityImpactedByHeavyRain'] ?? false;

        return new self(
            eubwid: (string) $item['eubwidNotation'],
            samplingPoint: basename((string) ($point['_about'] ?? '')),
            kind: BathingWaterKind::fromTypes(is_array($types) ? $types : [$types]),
            name: (string) self::text($item['name'] ?? $item['label'] ?? ''),
            districts: array_values(array_filter(array_map(
                fn ($district) => is_array($district) ? self::text($district['name'] ?? null) : null,
                is_array($item['district'] ?? null) ? $item['district'] : [],
            ))),
            sewerageUndertaker: self::companyName(self::text($item['appointedSewerageUndertaker']['name'] ?? null)),
            latitude: (float) ($point['lat'] ?? 0),
            longitude: (float) ($point['long'] ?? 0),
            description: self::text($profile['bathingWaterDescription'] ?? null),
            classification: BathingWaterClassification::tryFrom((string) self::text($compliance['complianceClassification']['name'] ?? null)),
            classificationYear: isset($compliance['sampleYear']['ordinalYear']) ? (int) $compliance['sampleYear']['ordinalYear'] : null,
            riskLevel: PollutionRisk::tryFrom((string) self::text($risk['riskLevel']['name'] ?? null)),
            riskExpiresAt: $expires === null ? null : Carbon::parse($expires, self::SOURCE_TIMEZONE)->utc(),
            affectedByHeavyRain: filter_var(is_array($heavyRain) ? ($heavyRain['_value'] ?? false) : $heavyRain, FILTER_VALIDATE_BOOLEAN),
            seasonStartsOn: self::date($profile['seasonStartDate'] ?? null),
            seasonEndsOn: self::date($profile['seasonFinishDate'] ?? null),
            latestSample: $sample === null ? null : BathingWaterSampleData::fromEnvironmentAgency($sample),
            agency: $agency,
            profileUrl: $profileUrl,
        );
    }

    /**
     * The plain value of a linked data literal, which may be a bare value,
     * a {"_value": ...} object or a list of language variants.
     */
    private static function text(mixed $value): ?string
    {
        if (is_array($value) && array_is_list($value)) {
            $value = $value[0] ?? null;
        }

        if (is_array($value)) {
            $value = $value['_value'] ?? null;
        }

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private static function date(mixed $value): ?Carbon
    {
        $text = self::text($value);

        return $text === null ? null : Carbon::parse($text)->startOfDay();
    }

    /**
     * "Yorkshire Water Services Limited" reads as "Yorkshire Water".
     */
    private static function companyName(?string $name): ?string
    {
        return $name === null ? null : trim((string) preg_replace('/( Services)?( Limited| Ltd| plc)$/i', '', $name));
    }
}
