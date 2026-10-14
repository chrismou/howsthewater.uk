<?php

namespace App\Models;

use App\Services\Geo\Coordinates;
use Database\Factories\RainfallStationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An Environment Agency tipping bucket rain gauge. Spots link to the
 * nearest one, so rainfall is context for the spot's surroundings rather
 * than a catchment total.
 *
 * @property int $id
 * @property string $external_id
 * @property string $label
 * @property string|null $grid_reference
 * @property float $latitude
 * @property float $longitude
 * @property string|null $measure_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RainfallStation extends Model
{
    /** @use HasFactory<RainfallStationFactory> */
    use HasFactory;

    protected $fillable = [
        'external_id',
        'label',
        'grid_reference',
        'latitude',
        'longitude',
        'measure_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function distanceMetresFrom(float $latitude, float $longitude): int
    {
        return (int) round(Coordinates::distanceMetres($this->latitude, $this->longitude, $latitude, $longitude));
    }

    /** @return HasMany<RainfallReading, $this> */
    public function readings(): HasMany
    {
        return $this->hasMany(RainfallReading::class)->orderByDesc('measured_at');
    }

    /** @return HasMany<Spot, $this> */
    public function spots(): HasMany
    {
        return $this->hasMany(Spot::class);
    }
}
