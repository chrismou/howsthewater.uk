<?php

namespace App\Models;

use Database\Factories\GaugeReadingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $gauge_station_id
 * @property Carbon $measured_at
 * @property float $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GaugeStation $gaugeStation
 */
class GaugeReading extends Model
{
    /** @use HasFactory<GaugeReadingFactory> */
    use HasFactory;

    protected $fillable = [
        'gauge_station_id',
        'measured_at',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
            'value' => 'float',
        ];
    }

    /** @return BelongsTo<GaugeStation, $this> */
    public function gaugeStation(): BelongsTo
    {
        return $this->belongsTo(GaugeStation::class);
    }
}
