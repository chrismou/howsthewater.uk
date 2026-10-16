<?php

namespace App\Models;

use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * A region groups the rivers we cover and scopes their URLs, since river
 * and spot names repeat across Britain.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property array<int, string>|null $overflow_feeds Keys of services.overflow_feeds
 * @property array<int, string>|null $bathing_water_districts Districts whose coastal bathing waters belong here
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, River> $rivers
 * @property-read Collection<int, Lake> $lakes
 * @property-read Collection<int, BathingWater> $bathingWaters
 */
class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'overflow_feeds',
        'bathing_water_districts',
    ];

    protected function casts(): array
    {
        return [
            'overflow_feeds' => 'array',
            'bathing_water_districts' => 'array',
        ];
    }

    /**
     * The storm overflow feeds every region needs, keyed by config key, so
     * ingest polls only the water companies we actually cover.
     *
     * @return array<string, array{name: string, driver: string, url: string}>
     */
    public static function overflowFeeds(): array
    {
        $keys = static::query()->pluck('overflow_feeds')->flatten()->filter()->unique()->all();

        return Arr::only(self::feedConfig(), $keys);
    }

    /**
     * Names of the water companies whose monitors cover this region.
     *
     * @return array<int, string>
     */
    public function waterCompanies(): array
    {
        return array_column($this->overflowFeedConfig(), 'name');
    }

    /**
     * The overflow feeds covering this region, keyed by config key.
     *
     * @return array<string, array{name: string, driver: string, url: string}>
     */
    public function overflowFeedConfig(): array
    {
        return Arr::only(self::feedConfig(), $this->overflow_feeds ?? []);
    }

    /**
     * The configured feeds, skipping any entry without a name and URL. A feed without a driver is a Stream feed.
     *
     * @return array<string, array{name: string, driver: string, url: string}>
     */
    private static function feedConfig(): array
    {
        $feeds = [];

        foreach (config()->array('services.overflow_feeds') as $key => $feed) {
            if (is_string($key) && is_array($feed) && is_string($feed['name'] ?? null) && is_string($feed['url'] ?? null)) {
                $feeds[$key] = ['name' => $feed['name'], 'driver' => is_string($feed['driver'] ?? null) ? $feed['driver'] : 'stream', 'url' => $feed['url']];
            }
        }

        return $feeds;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Lake, $this> */
    public function lakes(): HasMany
    {
        return $this->hasMany(Lake::class)->orderBy('name');
    }

    /** @return HasMany<BathingWater, $this> */
    public function bathingWaters(): HasMany
    {
        return $this->hasMany(BathingWater::class)->orderBy('name');
    }

    /**
     * Coastal and estuary bathing waters. Named for route model binding,
     * which scopes {beach} to its region through this relation.
     *
     * @return HasMany<BathingWater, $this>
     */
    public function beaches(): HasMany
    {
        return $this->bathingWaters()->beaches();
    }

    /** @return HasMany<River, $this> */
    public function rivers(): HasMany
    {
        return $this->hasMany(River::class)->orderBy('name');
    }

    /**
     * Rivers with pages, grouped by catchment for the navigation menu.
     * Larger catchments come first, then the order rivers were added.
     *
     * @return SupportCollection<string, SupportCollection<int, River>>
     */
    public function riversByCatchment(): SupportCollection
    {
        return $this->rivers()
            ->main()
            ->withoutCentreline()
            ->get()
            ->toBase()
            ->groupBy(fn (River $river) => $river->catchment ?? $this->name)
            ->sortBy([
                fn (SupportCollection $a, SupportCollection $b) => $b->count() <=> $a->count(),
                fn (SupportCollection $a, SupportCollection $b) => $a->min('id') <=> $b->min('id'),
            ]);
    }
}
