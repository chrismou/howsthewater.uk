<?php

namespace App\Models;

use Database\Factories\RainfallReadingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rainfall_station_id
 * @property Carbon $measured_at
 * @property float $millimetres
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RainfallStation $rainfallStation
 */
class RainfallReading extends Model
{
    /** @use HasFactory<RainfallReadingFactory> */
    use HasFactory;

    protected $fillable = [
        'rainfall_station_id',
        'measured_at',
        'millimetres',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
            'millimetres' => 'float',
        ];
    }

    /** @return BelongsTo<RainfallStation, $this> */
    public function rainfallStation(): BelongsTo
    {
        return $this->belongsTo(RainfallStation::class);
    }
}
