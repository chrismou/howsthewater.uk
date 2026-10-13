<?php

namespace App\Models;

use Database\Factories\RiverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $catchment
 * @property array<int, string>|null $aliases
 * @property array<int, array{0: float, 1: float}>|null $centreline
 * @property int|null $length_metres
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class River extends Model
{
    /** @use HasFactory<RiverFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'catchment',
        'aliases',
        'centreline',
        'length_metres',
    ];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'centreline' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Spot, $this> */
    public function spots(): HasMany
    {
        return $this->hasMany(Spot::class)->orderBy('river_metres');
    }

    /** @return HasMany<OverflowMonitor, $this> */
    public function overflowMonitors(): HasMany
    {
        return $this->hasMany(OverflowMonitor::class)->orderBy('river_metres');
    }

    /** @return HasMany<GaugeStation, $this> */
    public function gaugeStations(): HasMany
    {
        return $this->hasMany(GaugeStation::class)->orderBy('river_metres');
    }

    public function hasCentreline(): bool
    {
        return is_array($this->centreline) && count($this->centreline) > 1;
    }

    /**
     * Whether a receiving watercourse name from a source feed refers to this river.
     */
    public function matchesWatercourse(?string $watercourse): bool
    {
        if ($watercourse === null) {
            return false;
        }

        $normalised = mb_strtolower(trim($watercourse));

        foreach ($this->aliases ?? [] as $alias) {
            if ($normalised === mb_strtolower($alias)) {
                return true;
            }
        }

        return false;
    }
}
