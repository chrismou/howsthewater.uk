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
use InvalidArgumentException;

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

    /**
     * Top-level pages whose routes sit before /{region}, so a region with
     * one of these slugs could never be reached.
     */
    public const RESERVED_SLUGS = ['rivers', 'beaches', 'lakes', 'about', 'privacy', 'terms', 'search'];

    protected $fillable = [
        'name',
        'slug',
        'overflow_feeds',
        'bathing_water_districts',
    ];

    protected static function booted(): void
    {
        static::saving(function (Region $region) {
            if (in_array($region->slug, self::RESERVED_SLUGS, true)) {
                throw new InvalidArgumentException("The region slug [{$region->slug}] is taken by a top-level page.");
            }
        });
    }

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
     * @return array<string, array{name: string, driver: string, url: string, updated_on_change: bool}>
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
     * The name as it reads mid-sentence: "the Midlands", "the North East",
     * but "Yorkshire" and "Wales".
     */
    public function nameInSentence(): string
    {
        return preg_match('/^(north|south|east|west|midlands|thames valley)\b/i', $this->name) === 1 ? 'the '.$this->name : $this->name;
    }

    /**
     * Whether we read any water company overflow feed for this region. A
     * region without one (Wales, until Welsh Water's feed is added) says
     * its overflow data is not available, never that there are no spills.
     */
    public function hasOverflowData(): bool
    {
        return $this->overflowFeedConfig() !== [];
    }

    /**
     * The water company and live map for a region whose overflow feed is not read yet (services.unread_overflow_maps),
     * so its pages can point there instead. Null once a feed is read, or for a region not listed.
     *
     * @return array{name: string, url: string}|null
     */
    public function unreadOverflowMap(): ?array
    {
        $map = config("services.unread_overflow_maps.{$this->slug}");

        if ($this->hasOverflowData() || ! is_array($map) || ! is_string($map['name'] ?? null) || ! is_string($map['url'] ?? null)) {
            return null;
        }

        return ['name' => $map['name'], 'url' => $map['url']];
    }

    /**
     * The overflow feeds covering this region, keyed by config key.
     *
     * @return array<string, array{name: string, driver: string, url: string, updated_on_change: bool}>
     */
    public function overflowFeedConfig(): array
    {
        return Arr::only(self::feedConfig(), $this->overflow_feeds ?? []);
    }

    /**
     * The configured feeds, skipping any entry without a name and URL. A feed without a driver is a Stream feed.
     * `updated_on_change` marks a feed whose timestamps only move when a monitor changes (SyncOverflowMonitors).
     *
     * @return array<string, array{name: string, driver: string, url: string, updated_on_change: bool}>
     */
    private static function feedConfig(): array
    {
        $feeds = [];

        foreach (config()->array('services.overflow_feeds') as $key => $feed) {
            if (is_string($key) && is_array($feed) && is_string($feed['name'] ?? null) && is_string($feed['url'] ?? null)) {
                $feeds[$key] = ['name' => $feed['name'], 'driver' => is_string($feed['driver'] ?? null) ? $feed['driver'] : 'stream', 'url' => $feed['url'], 'updated_on_change' => ($feed['updated_on_change'] ?? false) === true];
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
