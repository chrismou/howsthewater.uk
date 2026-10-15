<?php

namespace App\Services\Conditions;

use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\Region;
use App\Models\River;
use Illuminate\Support\Facades\Cache;

/**
 * The Rivers, Beaches and Lakes menus as plain arrays, built once and
 * cached, since every page renders them. The scheduled regions:refresh
 * rebuilds them in place, so seeded places appear within a quarter hour.
 */
class NavigationMenus
{
    private const KEY_PREFIX = 'navigation';

    private const TTL_HOURS = 6;

    /**
     * @return array{rivers: list<array<string, mixed>>, beaches: list<array<string, mixed>>, lakes: list<array<string, mixed>>}
     */
    public function all(): array
    {
        return Cache::remember($this->key(), now()->addHours(self::TTL_HOURS), fn () => $this->build());
    }

    /**
     * Rebuilds the menus in place, so no page has to build them.
     */
    public function refresh(): void
    {
        Cache::put($this->key(), $this->build(), now()->addHours(self::TTL_HOURS));
    }

    /**
     * Each menu lists regions with something in it; each entry carries the
     * region's slug and name, its overview link and its places grouped
     * under headings (catchment, district or simply "Lakes").
     *
     * @return array{rivers: list<array<string, mixed>>, beaches: list<array<string, mixed>>, lakes: list<array<string, mixed>>}
     */
    public function build(): array
    {
        $regions = Region::query()
            ->orderBy('name')
            ->with(['lakes', 'bathingWaters' => fn ($query) => $query->beaches()])
            ->get();

        $entry = fn (Region $region, string $url, string $blurb, array $groups) => [
            'slug' => $region->slug,
            'name' => $region->name,
            'url' => $url,
            'blurb' => $blurb,
            'groups' => $groups,
        ];

        $menus = ['rivers' => [], 'beaches' => [], 'lakes' => []];

        foreach ($regions as $region) {
            $rivers = $region->riversByCatchment()
                ->map(fn ($rivers) => $rivers->map(fn (River $river) => ['name' => $river->name, 'url' => route('rivers.show', [$region, $river])])->values()->all())
                ->all();

            $beaches = $region->bathingWaters
                ->groupBy(fn (BathingWater $beach) => $beach->district ?? $region->name)
                ->sortKeys()
                ->map(fn ($beaches) => $beaches->map(fn (BathingWater $beach) => ['name' => $beach->name, 'url' => route('beaches.show', [$region, $beach])])->values()->all())
                ->all();

            $lakes = $region->lakes->map(fn (Lake $lake) => ['name' => $lake->name, 'url' => route('lakes.show', [$region, $lake])])->values()->all();

            if ($rivers !== []) {
                $menus['rivers'][] = $entry($region, route('regions.show', $region), 'map, river strips and every spot', $rivers);
            }

            if ($beaches !== []) {
                $menus['beaches'][] = $entry($region, route('beaches.index', $region), 'every beach, its classification and nearby overflows', $beaches);
            }

            if ($lakes !== []) {
                $menus['lakes'][] = $entry($region, route('lakes.index', $region), 'every lake, its bathing sites and level', ['Lakes' => $lakes]);
            }
        }

        return $menus;
    }

    private function key(): string
    {
        return self::KEY_PREFIX.':'.ConditionsCache::codeVersion();
    }
}
