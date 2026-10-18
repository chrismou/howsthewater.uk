<?php

namespace App\Actions\Ingest;

use App\Data\BathingWaterData;
use App\Data\BathingWaterSampleData;
use App\Enums\BathingWaterKind;
use App\Models\BathingWater;
use App\Models\BathingWaterSample;
use App\Models\Lake;
use App\Models\Region;
use App\Models\Spot;
use App\Services\EnvironmentAgency\BathingWaterClient;
use App\Services\Geo\Coordinates;
use App\Services\Sepa\BathingWaterClient as SepaBathingWaterClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reads the whole bathing water register in one request and keeps the
 * sites we cover up to date: beaches in a region's districts (or, with no
 * district, near a beach that is), lake sites
 * on a seeded lake, and river sites on one of our spots. Each run stores
 * the latest sample, so sample history builds up between history fetches.
 * Scotland's sites come from SEPA's map service, without samples, which
 * SyncSepaBathingWaterSamples reads from each site's results page.
 */
class SyncBathingWaters
{
    /**
     * A river bathing water further than this from every spot is not ours.
     */
    public const MAX_SPOT_DISTANCE_METRES = 1500;

    /**
     * A beach the register gives no district (Canvey Island Foreshore) takes
     * the region of the nearest placed beach within this distance.
     */
    public const NEAREST_BEACH_METRES = 15000;

    public function __construct(private BathingWaterClient $client, private SepaBathingWaterClient $sepa)
    {
    }

    /**
     * The Environment Agency's register for England.
     *
     * @return array{sites: int, samples: int}
     */
    public function handle(): array
    {
        return $this->store($this->client->all());
    }

    /**
     * Wales's register, published for Natural Resources Wales on the EA's platform in the same shape.
     *
     * @return array{sites: int, samples: int}
     */
    public function handleWales(): array
    {
        return $this->store($this->client->wales()->all());
    }

    /**
     * SEPA's bathing waters for Scotland.
     *
     * @return array{sites: int, samples: int}
     */
    public function handleSepa(): array
    {
        return $this->store($this->sepa->all());
    }

    /**
     * @param  Collection<int, BathingWaterData>  $register
     * @return array{sites: int, samples: int}
     */
    private function store(Collection $register): array
    {
        $regions = Region::query()->get();
        $lakes = Lake::query()->get();
        $spots = Spot::query()->published()->onMainRiver()->with('river')->get();

        $sites = 0;
        $samples = 0;
        $placements = $register->mapWithKeys(fn (BathingWaterData $data) => [$data->eubwid => $this->placement($data, $regions, $lakes, $spots)]);

        foreach ($register as $data) {
            $placement = $placements[$data->eubwid] ?? $this->nearestBeachPlacement($data, $register, $placements);

            if ($placement === null) {
                continue;
            }

            $bathingWater = BathingWater::query()->updateOrCreate(['eubwid' => $data->eubwid], [
                ...$placement,
                'agency' => $data->agency,
                'sampling_point' => $data->samplingPoint,
                'kind' => $data->kind,
                'name' => $data->name,
                'slug' => $this->uniqueSlug($data, $placement['region_id']),
                'district' => $data->districts[0] ?? null,
                'sewerage_undertaker' => $data->sewerageUndertaker,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'description' => $data->description,
                'profile_url' => $data->profileUrl,
                'classification' => $data->classification,
                'classification_year' => $data->classificationYear,
                'risk_level' => $data->riskLevel,
                'risk_expires_at' => $data->riskExpiresAt,
                'affected_by_heavy_rain' => $data->affectedByHeavyRain,
                'season_starts_on' => $data->seasonStartsOn,
                'season_ends_on' => $data->seasonEndsOn,
            ]);

            $sites++;

            if ($data->latestSample !== null) {
                $samples += $this->storeSamples($bathingWater, collect([$data->latestSample]));
            }
        }

        return ['sites' => $sites, 'samples' => $samples];
    }

    /**
     * The site's name as a slug, with its sampling point appended if another
     * site in the same region already has that slug, since beach URLs are
     * only unique within their region.
     */
    private function uniqueSlug(BathingWaterData $data, int $regionId): string
    {
        $slug = Str::slug($data->name);

        $taken = BathingWater::query()
            ->where('region_id', $regionId)
            ->where('slug', $slug)
            ->where('eubwid', '!=', $data->eubwid)
            ->exists();

        return $taken ? "{$slug}-{$data->samplingPoint}" : $slug;
    }

    /**
     * Stores samples, ignoring any already held.
     *
     * @param  Collection<int, BathingWaterSampleData>  $samples
     */
    public function storeSamples(BathingWater $bathingWater, Collection $samples): int
    {
        $rows = $samples->map(fn (BathingWaterSampleData $sample) => [
            'bathing_water_id' => $bathingWater->id,
            'sampled_at' => $sample->sampledAt,
            'e_coli' => $sample->eColi,
            'e_coli_qualifier' => $sample->eColiQualifier,
            'enterococci' => $sample->enterococci,
            'enterococci_qualifier' => $sample->enterococciQualifier,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        return $rows === [] ? 0 : BathingWaterSample::query()->insertOrIgnore($rows);
    }

    /**
     * For a beach without a district, the region of the nearest beach the
     * register did place, within NEAREST_BEACH_METRES.
     *
     * @param  Collection<int, BathingWaterData>  $register
     * @param  Collection<string, array{region_id: int, lake_id: int|null, spot_id: int|null}|null>  $placements
     * @return array{region_id: int, lake_id: int|null, spot_id: int|null}|null
     */
    private function nearestBeachPlacement(BathingWaterData $data, Collection $register, Collection $placements): ?array
    {
        if ($data->kind?->isBeach() !== true || $data->districts !== []) {
            return null;
        }

        $nearest = $register
            ->filter(fn (BathingWaterData $other) => $other->kind?->isBeach() === true && ($placements[$other->eubwid] ?? null) !== null)
            ->map(fn (BathingWaterData $other) => ['eubwid' => $other->eubwid, 'distance' => Coordinates::distanceMetres($data->latitude, $data->longitude, $other->latitude, $other->longitude)])
            ->filter(fn (array $candidate) => $candidate['distance'] <= self::NEAREST_BEACH_METRES)
            ->sortBy('distance')
            ->first();

        return $nearest === null ? null : $placements[$nearest['eubwid']];
    }

    /**
     * Where a site belongs, or null when it is outside everything we cover.
     *
     * @param  Collection<int, Region>  $regions
     * @param  Collection<int, Lake>  $lakes
     * @param  Collection<int, Spot>  $spots
     * @return array{region_id: int, lake_id: int|null, spot_id: int|null}|null
     */
    private function placement(BathingWaterData $data, Collection $regions, Collection $lakes, Collection $spots): ?array
    {
        if ($data->kind === null) {
            return null;
        }

        if ($data->kind->isBeach()) {
            $region = $regions->first(fn (Region $region) => array_intersect($data->districts, $region->bathing_water_districts ?? []) !== []);

            return $region === null ? null : ['region_id' => $region->id, 'lake_id' => null, 'spot_id' => null];
        }

        if ($data->kind === BathingWaterKind::Lake) {
            $lake = $lakes->first(fn (Lake $lake) => $lake->matchesBathingWaterName($data->lakeName ?? $data->name));

            return $lake === null ? null : ['region_id' => $lake->region_id, 'lake_id' => $lake->id, 'spot_id' => null];
        }

        $nearest = $spots
            ->map(fn (Spot $spot) => ['spot' => $spot, 'distance' => Coordinates::distanceMetres($data->latitude, $data->longitude, $spot->latitude, $spot->longitude)])
            ->filter(fn (array $candidate) => $candidate['distance'] <= self::MAX_SPOT_DISTANCE_METRES)
            ->sortBy('distance')
            ->first();

        return $nearest === null ? null : ['region_id' => $nearest['spot']->river->region_id, 'lake_id' => null, 'spot_id' => $nearest['spot']->id];
    }
}
