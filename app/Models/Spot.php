<?php

namespace App\Models;

use Database\Factories\SpotFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $river_id
 * @property int|null $gauge_station_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property float $latitude
 * @property float $longitude
 * @property int|null $river_metres
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read River $river
 * @property-read GaugeStation|null $gaugeStation
 */
class Spot extends Model
{
    /** @use HasFactory<SpotFactory> */
    use HasFactory;

    protected $fillable = [
        'river_id',
        'gauge_station_id',
        'name',
        'slug',
        'description',
        'latitude',
        'longitude',
        'river_metres',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_published' => 'boolean',
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

    /**
     * @param  Builder<Spot>  $query
     * @return Builder<Spot>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
