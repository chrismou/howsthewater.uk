<?php

namespace App\Models;

use App\Enums\Agency;
use App\Enums\BathingWaterClassification;
use App\Enums\BathingWaterKind;
use App\Enums\PollutionRisk;
use Database\Factories\BathingWaterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An Environment Agency designated bathing water. Beaches have their own
 * pages; lake and river sites show on their lake or spot.
 *
 * @property int $id
 * @property string $eubwid
 * @property Agency $agency
 * @property string $sampling_point
 * @property int|null $region_id
 * @property int|null $lake_id
 * @property int|null $spot_id
 * @property BathingWaterKind $kind
 * @property string $name
 * @property string $slug
 * @property string|null $district
 * @property string|null $sewerage_undertaker
 * @property float $latitude
 * @property float $longitude
 * @property string|null $description
 * @property string|null $profile_url
 * @property BathingWaterClassification|null $classification
 * @property int|null $classification_year
 * @property PollutionRisk|null $risk_level
 * @property Carbon|null $risk_expires_at
 * @property bool $affected_by_heavy_rain
 * @property Carbon|null $season_starts_on
 * @property Carbon|null $season_ends_on
 * @property Carbon|null $history_fetched_at
 * @property int|null $rainfall_station_id
 * @property Carbon|null $rain_gauges_searched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RainfallStation|null $rainfallStation
 * @property-read Region|null $region
 * @property-read Lake|null $lake
 * @property-read Spot|null $spot
 * @property-read Collection<int, BathingWaterSample> $samples
 * @property-read Collection<int, BathingWaterClassificationRecord> $classifications
 */
class BathingWater extends Model
{
    /** @use HasFactory<BathingWaterFactory> */
    use HasFactory;

    protected $fillable = [
        'eubwid',
        'agency',
        'sampling_point',
        'region_id',
        'lake_id',
        'spot_id',
        'kind',
        'name',
        'slug',
        'district',
        'sewerage_undertaker',
        'latitude',
        'longitude',
        'description',
        'profile_url',
        'classification',
        'classification_year',
        'risk_level',
        'risk_expires_at',
        'affected_by_heavy_rain',
        'season_starts_on',
        'season_ends_on',
        'history_fetched_at',
        'rainfall_station_id',
        'rain_gauges_searched_at',
    ];

    protected function casts(): array
    {
        return [
            'agency' => Agency::class,
            'kind' => BathingWaterKind::class,
            'classification' => BathingWaterClassification::class,
            'risk_level' => PollutionRisk::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'risk_expires_at' => 'datetime',
            'affected_by_heavy_rain' => 'boolean',
            'season_starts_on' => 'date',
            'season_ends_on' => 'date',
            'history_fetched_at' => 'datetime',
            'rain_gauges_searched_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return BelongsTo<Lake, $this> */
    public function lake(): BelongsTo
    {
        return $this->belongsTo(Lake::class);
    }

    /** @return BelongsTo<Spot, $this> */
    public function spot(): BelongsTo
    {
        return $this->belongsTo(Spot::class);
    }

    /** @return BelongsTo<RainfallStation, $this> */
    public function rainfallStation(): BelongsTo
    {
        return $this->belongsTo(RainfallStation::class);
    }

    /** @return HasMany<BathingWaterSample, $this> */
    public function samples(): HasMany
    {
        return $this->hasMany(BathingWaterSample::class)->orderByDesc('sampled_at');
    }

    /** @return HasMany<BathingWaterClassificationRecord, $this> */
    public function classifications(): HasMany
    {
        return $this->hasMany(BathingWaterClassificationRecord::class)->orderByDesc('year');
    }

    /**
     * Coastal and estuary bathing waters, which get their own pages.
     *
     * @param  Builder<BathingWater>  $query
     * @return Builder<BathingWater>
     */
    public function scopeBeaches(Builder $query): Builder
    {
        return $query->whereIn('kind', [BathingWaterKind::Coastal->value, BathingWaterKind::Estuary->value]);
    }

    /**
     * The name as people search for a beach: "Formby beach", but never
     * "Robin Hood's Bay beach" or "Walney West Shore beach".
     */
    public function beachSearchName(): string
    {
        return preg_match('/\b(beach|bay|sands?|shore)$/i', $this->name) === 1 ? $this->name : $this->name.' beach';
    }

    /**
     * Whether today falls in the bathing season, when the EA samples weekly
     * and publishes daily pollution risk forecasts. The season dates are UK
     * calendar dates, so today is taken in UK time, not UTC.
     */
    public function isInSeason(?Carbon $on = null): bool
    {
        if ($this->season_starts_on === null || $this->season_ends_on === null) {
            return false;
        }

        $today = ($on ?? now())->copy()->timezone(config('app.display_timezone', 'Europe/London'))->toDateString();

        return $today >= $this->season_starts_on->toDateString() && $today <= $this->season_ends_on->toDateString();
    }
}
