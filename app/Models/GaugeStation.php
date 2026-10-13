<?php

namespace App\Models;

use Database\Factories\GaugeStationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $external_id
 * @property int|null $river_id
 * @property string $label
 * @property string|null $river_name
 * @property string|null $town
 * @property float $latitude
 * @property float $longitude
 * @property int|null $river_metres
 * @property string|null $measure_id
 * @property float|null $typical_range_low
 * @property float|null $typical_range_high
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read River|null $river
 */
class GaugeStation extends Model
{
    /** @use HasFactory<GaugeStationFactory> */
    use HasFactory;

    protected $fillable = [
        'external_id',
        'river_id',
        'label',
        'river_name',
        'town',
        'latitude',
        'longitude',
        'river_metres',
        'measure_id',
        'typical_range_low',
        'typical_range_high',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'typical_range_low' => 'float',
            'typical_range_high' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<River, $this> */
    public function river(): BelongsTo
    {
        return $this->belongsTo(River::class);
    }

    /** @return HasMany<GaugeReading, $this> */
    public function readings(): HasMany
    {
        return $this->hasMany(GaugeReading::class)->orderByDesc('measured_at');
    }

    /** @return HasMany<Spot, $this> */
    public function spots(): HasMany
    {
        return $this->hasMany(Spot::class);
    }
}
