<?php

namespace App\Data;

use App\Enums\PollutionRisk;
use App\Models\BathingWater;
use App\Models\BathingWaterClassificationRecord;
use App\Models\BathingWaterSample;
use Illuminate\Support\Collection;

/**
 * A designated bathing water's classification, samples and forecast, as
 * the Environment Agency reports them. Shown on beach, lake and spot pages.
 */
final readonly class BathingWaterSummary
{
    /**
     * @param  Collection<int, BathingWaterSample>  $recentSamples  Newest first
     * @param  Collection<int, BathingWaterClassificationRecord>  $classifications  Newest year first
     */
    public function __construct(
        public BathingWater $bathingWater,
        public Collection $recentSamples,
        public Collection $classifications,
    ) {
    }

    public function latestSample(): ?BathingWaterSample
    {
        return $this->recentSamples->first();
    }

    /**
     * Today's pollution risk forecast, or null outside the season or once
     * the last forecast has expired. Evaluated at read time.
     */
    public function currentRisk(): ?PollutionRisk
    {
        $bathingWater = $this->bathingWater;

        if ($bathingWater->risk_level === null || $bathingWater->risk_expires_at === null || $bathingWater->risk_expires_at->isPast()) {
            return null;
        }

        return $bathingWater->isInSeason() ? $bathingWater->risk_level : null;
    }

    public function url(): string
    {
        return 'https://environment.data.gov.uk/bwq/profiles/profile.html?site='.rawurlencode($this->bathingWater->eubwid);
    }
}
