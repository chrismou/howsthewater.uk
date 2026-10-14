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
 * @property array<int, string>|null $overflow_feeds Keys of services.stream.feeds
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
     * @return array<string, array{name: string, url: string}>
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
        return array_column(Arr::only(self::feedConfig(), $this->overflow_feeds ?? []), 'name');
    }

    /**
     * The configured feeds, skipping any entry without a name and URL.
     *
     * @return array<string, array{name: string, url: string}>
     */
    private static function feedConfig(): array
    {
        $feeds = [];

        foreach (config()->array('services.stream.feeds') as $key => $feed) {
            if (is_string($key) && is_array($feed) && is_string($feed['name'] ?? null) && is_string($feed['url'] ?? null)) {
                $feeds[$key] = ['name' => $feed['name'], 'url' => $feed['url']];
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
