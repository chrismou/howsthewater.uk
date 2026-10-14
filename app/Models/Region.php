<?php

namespace App\Models;

use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * A region groups the rivers we cover and scopes their URLs, since river
 * and spot names repeat across Britain.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, River> $rivers
 */
class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
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
