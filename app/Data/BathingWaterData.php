<?php

namespace App\Data;

use App\Enums\BathingWaterClassification;
use App\Enums\BathingWaterKind;
use App\Enums\PollutionRisk;
use Illuminate\Support\Carbon;

/**
 * One site from the EA designated bathing water register, with its latest
 * classification, sample and pollution risk forecast.
 */
final readonly class BathingWaterData
{
    /**
     * The EA publishes forecast times in UK local time without an offset.
     */
    private const SOURCE_TIMEZONE = 'Europe/London';

    /**
     * @param  array<int, string>  $districts
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
    ) {
    }

    /**
     * @param  array<string, mixed>  $item  A bathing water from the EA register
     */
    public static function fromEnvironmentAgency(array $item): self
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
            name: (string) self::text($item['name'] ?? ''),
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
