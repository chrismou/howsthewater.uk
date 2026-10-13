<?php

namespace App\Models;

use App\Services\Geo\WatercourseName;
use Database\Factories\RiverFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $parent_river_id
 * @property string $name
 * @property string $slug
 * @property string|null $catchment
 * @property array<int, string>|null $aliases
 * @property array<int, array{0: float, 1: float}>|null $centreline
 * @property int|null $length_metres
 * @property int|null $joins_at_metres
 * @property-read River|null $parent
 * @property-read Collection<int, River> $tributaries
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class River extends Model
{
    /** @use HasFactory<RiverFactory> */
    use HasFactory;

    protected $fillable = [
        'parent_river_id',
        'name',
        'slug',
        'catchment',
        'aliases',
        'centreline',
        'length_metres',
        'joins_at_metres',
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

    /** @return BelongsTo<River, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(River::class, 'parent_river_id');
    }

    /** @return HasMany<River, $this> */
    public function tributaries(): HasMany
    {
        return $this->hasMany(River::class, 'parent_river_id')->orderBy('joins_at_metres');
    }

    /**
     * Rivers that get their own pages, as opposed to tributaries that only
     * contribute monitors.
     *
     * @param  Builder<River>  $query
     * @return Builder<River>
     */
    public function scopeMain(Builder $query): Builder
    {
        return $query->whereNull('parent_river_id');
    }

    public function isTributary(): bool
    {
        return $this->parent_river_id !== null;
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
     * Whether a receiving watercourse name from a source feed refers to this
     * river, either directly or as an unnamed tributary of it.
     */
    public function matchesWatercourse(?string $watercourse): bool
    {
        $parsed = WatercourseName::parse($watercourse);

        if ($parsed === null) {
            return false;
        }

        foreach ($this->aliases ?? [] as $alias) {
            if ($parsed->matches($alias)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The mouth of the river: the last point of its centreline.
     *
     * @return array{0: float, 1: float}|null
     */
    public function mouth(): ?array
    {
        if (! $this->hasCentreline()) {
            return null;
        }

        $centreline = $this->centreline;

        return end($centreline);
    }
}
