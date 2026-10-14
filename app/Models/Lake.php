<?php

namespace App\Models;

use Database\Factories\LakeFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A lake or pond seeded per region. Its designated bathing water sites,
 * level gauge and nearby overflows make up its page.
 *
 * @property int $id
 * @property int $region_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property float $latitude
 * @property float $longitude
 * @property string|null $gauge_reference
 * @property int|null $gauge_station_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Region $region
 * @property-read GaugeStation|null $gaugeStation
 * @property-read Collection<int, BathingWater> $bathingWaters
 */
class Lake extends Model
{
    /** @use HasFactory<LakeFactory> */
    use HasFactory;

    protected $fillable = [
        'region_id',
        'name',
        'slug',
        'description',
        'latitude',
        'longitude',
        'gauge_reference',
        'gauge_station_id',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
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

    /** @return BelongsTo<GaugeStation, $this> */
    public function gaugeStation(): BelongsTo
    {
        return $this->belongsTo(GaugeStation::class);
    }

    /** @return HasMany<BathingWater, $this> */
    public function bathingWaters(): HasMany
    {
        return $this->hasMany(BathingWater::class)->orderBy('name');
    }

    /**
     * Whether a bathing water's name refers to this lake, e.g.
     * "Windermere, Fellfoot" or "Hampstead Heath (Mixed Pond)".
     */
    public function matchesBathingWaterName(string $name): bool
    {
        return str_starts_with(mb_strtolower($name), mb_strtolower($this->matchName()));
    }

    /**
     * The part of the lake's name bathing water names start with.
     */
    private function matchName(): string
    {
        return (string) preg_replace('/ Ponds$/', '', $this->name);
    }
}
