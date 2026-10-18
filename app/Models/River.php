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
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $region_id
 * @property int|null $parent_river_id
 * @property int|null $flows_into_river_id
 * @property string $name
 * @property string|null $os_name
 * @property string $slug
 * @property string|null $catchment
 * @property array<int, string>|null $aliases
 * @property array<string, array<int, string>>|null $also_runs_through
 * @property float|null $mouth_latitude
 * @property float|null $mouth_longitude
 * @property array<int, array{0: float, 1: float}>|null $centreline
 * @property int|null $length_metres
 * @property int|null $joins_at_metres
 * @property Carbon|null $gauges_discovered_at
 * @property-read Region|null $region
 * @property-read River|null $parent
 * @property-read River|null $flowsInto
 * @property-read Collection<int, River> $tributaries
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ShareImage|null $shareImage
 */
class River extends Model
{
    /** @use HasFactory<RiverFactory> */
    use HasFactory;

    protected $fillable = [
        'region_id',
        'parent_river_id',
        'flows_into_river_id',
        'name',
        'os_name',
        'slug',
        'catchment',
        'aliases',
        'also_runs_through',
        'mouth_latitude',
        'mouth_longitude',
        'centreline',
        'length_metres',
        'joins_at_metres',
        'gauges_discovered_at',
    ];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'also_runs_through' => 'array',
            'mouth_latitude' => 'float',
            'mouth_longitude' => 'float',
            'centreline' => 'array',
            'gauges_discovered_at' => 'datetime',
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
     * The main river this one joins, for a main river that is not the last in its system (the Ure joins the Ouse).
     *
     * @return BelongsTo<River, $this>
     */
    public function flowsInto(): BelongsTo
    {
        return $this->belongsTo(River::class, 'flows_into_river_id');
    }

    /**
     * Every river whose water reaches this one directly: its tributaries and any main rivers that join it.
     *
     * @return Builder<River>
     */
    public function inflowingRivers(): Builder
    {
        return River::query()->where(fn (Builder $query) => $query->where('parent_river_id', $this->id)->orWhere('flows_into_river_id', $this->id));
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

    /**
     * Every column except the centreline, which can run to thousands of
     * points and is only needed for maps and positioning. Use this wherever
     * rivers end up inside cached spot conditions.
     *
     * @param  Builder<River>  $query
     * @return Builder<River>
     */
    public function scopeWithoutCentreline(Builder $query): Builder
    {
        return $query->select(['id', 'region_id', 'parent_river_id', 'flows_into_river_id', 'name', 'os_name', 'slug', 'catchment', 'also_runs_through', 'aliases', 'mouth_latitude', 'mouth_longitude', 'length_metres', 'joins_at_metres', 'created_at', 'updated_at']);
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
     * Whether a river name as SEPA, NRW and Southern Water usually write it,
     * without "River" or "Afon" ("Tweed", "Teifi", "MEDWAY", "Tributary of
     * the Medway"), refers to this river. Names that do carry either ("River
     * Cynon") match whichever form we seeded.
     */
    public function matchesBareName(?string $name): bool
    {
        $parsed = WatercourseName::parse($name);

        if ($parsed === null) {
            return false;
        }

        $bare = (string) preg_replace('/^(?:river|afon)\s+/', '', $parsed->name);
        $forms = [$bare, 'river '.$bare, 'afon '.$bare];

        foreach ($this->aliases ?? [] as $alias) {
            if (in_array(mb_strtolower(trim($alias)), $forms, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Where the river is expected to end, as seeded. Several rivers across
     * Britain share names like Calder or Derwent, so this picks the right one.
     *
     * @return array{0: float, 1: float}|null
     */
    public function mouthHint(): ?array
    {
        if ($this->mouth_latitude === null || $this->mouth_longitude === null) {
            return null;
        }

        return [$this->mouth_latitude, $this->mouth_longitude];
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

    /**
     * The card social sites show when this page is shared, once GenerateShareImages has drawn it.
     *
     * @return MorphOne<ShareImage, $this>
     */
    public function shareImage(): MorphOne
    {
        return $this->morphOne(ShareImage::class, 'shareable');
    }
}
