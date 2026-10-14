<?php

namespace App\Models;

use Database\Factories\SpotFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $river_id
 * @property int|null $gauge_station_id
 * @property int|null $rainfall_station_id
 * @property Carbon|null $rain_gauges_searched_at
 * @property Carbon|null $flood_areas_searched_at
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property float $latitude
 * @property float $longitude
 * @property int|null $river_metres
 * @property bool $is_published
 * @property bool $is_featured
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read River $river
 * @property-read GaugeStation|null $gaugeStation
 * @property-read RainfallStation|null $rainfallStation
 * @property-read Collection<int, FloodArea> $floodAreas
 */
class Spot extends Model
{
    /** @use HasFactory<SpotFactory> */
    use HasFactory;

    protected $fillable = [
        'river_id',
        'gauge_station_id',
        'rainfall_station_id',
        'rain_gauges_searched_at',
        'flood_areas_searched_at',
        'name',
        'slug',
        'description',
        'latitude',
        'longitude',
        'river_metres',
        'is_published',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'rain_gauges_searched_at' => 'datetime',
            'flood_areas_searched_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<River, $this> */
    public function river(): BelongsTo
    {
        return $this->belongsTo(River::class);
    }

    /** @return BelongsTo<GaugeStation, $this> */
    public function gaugeStation(): BelongsTo
    {
        return $this->belongsTo(GaugeStation::class);
    }

    /** @return BelongsTo<RainfallStation, $this> */
    public function rainfallStation(): BelongsTo
    {
        return $this->belongsTo(RainfallStation::class);
    }

    /** @return BelongsToMany<FloodArea, $this> */
    public function floodAreas(): BelongsToMany
    {
        return $this->belongsToMany(FloodArea::class);
    }

    /**
     * @param  Builder<Spot>  $query
     * @return Builder<Spot>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
