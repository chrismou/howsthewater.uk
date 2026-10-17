<?php

namespace App\Services\EnvironmentAgency;

use App\Data\BathingWaterData;
use App\Data\BathingWaterSampleData;
use App\Enums\Agency;
use App\Enums\BathingWaterClassification;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Reads the Environment Agency's designated bathing water register and
 * its sample and classification history (open data, no key required).
 * Natural Resources Wales publishes Wales's register on the same platform
 * and in the same shape, under /wales/bathing-waters; wales() reads that.
 */
class BathingWaterClient
{
    public function __construct(private string $dataset = '', private Agency $agency = Agency::EnvironmentAgency)
    {
    }

    /**
     * The same client reading Wales's register, published for NRW.
     */
    public function wales(): self
    {
        return new self('wales/bathing-waters', Agency::NaturalResourcesWales);
    }

    /**
     * The client for a bathing water's agency: Wales's register for NRW sites, England's otherwise.
     */
    public function forAgency(Agency $agency): self
    {
        return $agency === Agency::NaturalResourcesWales ? $this->wales() : $this;
    }

    /**
     * Every property the hourly sync needs, so the whole register comes back
     * in one request rather than one per site.
     */
    private const LIST_PROPERTIES = [
        'name', 'label', 'type', 'eubwidNotation', 'district.name', 'appointedSewerageUndertaker.name',
        'samplingPoint.lat', 'samplingPoint.long', 'waterQualityImpactedByHeavyRain',
        'latestComplianceAssessment.complianceClassification.name', 'latestComplianceAssessment.sampleYear.ordinalYear',
        'latestRiskPrediction.riskLevel.name', 'latestRiskPrediction.expiresAt',
        'latestProfile.bathingWaterDescription', 'latestProfile.seasonStartDate', 'latestProfile.seasonFinishDate',
        'latestSampleAssessment.sampleDateTime', 'latestSampleAssessment.escherichiaColiCount',
        'latestSampleAssessment.escherichiaColiQualifier.countQualifierNotation',
        'latestSampleAssessment.intestinalEnterococciCount',
        'latestSampleAssessment.intestinalEnterococciQualifier.countQualifierNotation',
    ];

    /**
     * @return Collection<int, BathingWaterData>
     */
    public function all(): Collection
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->request()
            ->get($this->url('doc/bathing-water.json'), [
                '_pageSize' => 1000,
                '_properties' => implode(',', self::LIST_PROPERTIES),
            ])
            ->throw()
            ->json('result.items', []);

        return collect($items)
            ->filter(fn (array $item) => isset($item['eubwidNotation']))
            ->map(fn (array $item) => BathingWaterData::fromEnvironmentAgency($item, $this->agency, $this->profileUrl($item['eubwidNotation'])))
            ->values();
    }

    /**
     * The most recent in-season samples for a site, newest first.
     *
     * @return Collection<int, BathingWaterSampleData>
     */
    public function samples(string $eubwid, int $limit = 40): Collection
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->request()
            ->get($this->url('doc/bathing-water-quality/in-season/sample.json'), [
                'bwq_bathingWater.eubwidNotation' => $eubwid,
                '_sort' => '-sampleDateTime',
                '_pageSize' => $limit,
            ])
            ->throw()
            ->json('result.items', []);

        return collect($items)
            ->map(fn (array $item) => BathingWaterSampleData::fromEnvironmentAgency($item))
            ->filter()
            ->values();
    }

    /**
     * Each year's classification for a sampling point.
     *
     * @return array<int, BathingWaterClassification>
     */
    public function classifications(string $samplingPoint): array
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->request()
            ->get($this->url("data/bathing-water-quality/compliance-rBWD/point/{$samplingPoint}.json"), ['_pageSize' => 100])
            ->throw()
            ->json('result.items', []);

        $years = [];

        foreach ($items as $item) {
            $year = $item['sampleYear']['ordinalYear'] ?? null;
            $name = $item['complianceClassification']['name']['_value'] ?? null;
            $classification = is_string($name) ? BathingWaterClassification::tryFrom($name) : null;

            if (is_numeric($year) && $classification !== null) {
                $years[(int) $year] = $classification;
            }
        }

        return $years;
    }

    /**
     * Wales's sites need their own profile link; England's use the summary's default.
     */
    private function profileUrl(mixed $eubwid): ?string
    {
        return $this->agency === Agency::NaturalResourcesWales && is_string($eubwid)
            ? $this->url('profiles/profile.html').'?site='.rawurlencode($eubwid)
            : null;
    }

    private function url(string $path): string
    {
        return collect([rtrim(config('services.environment_agency.bathing_water_url'), '/'), trim($this->dataset, '/'), ltrim($path, '/')])->filter()->join('/');
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(60)
            ->retry(2, 2000)
            ->withUserAgent(config('services.user_agent'));
    }
}
