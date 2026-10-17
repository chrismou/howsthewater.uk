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
     * @return array{rivers: list<array{slug: string, name: string, url: string, blurb: string, groups: array<string, list<array{name: string, url: string}>>}>, beaches: list<array{slug: string, name: string, url: string, blurb: string, groups: array<string, list<array{name: string, url: string}>>}>, lakes: list<array{slug: string, name: string, url: string, blurb: string, groups: array<string, list<array{name: string, url: string}>>}>}
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
     * @return array{rivers: list<array{slug: string, name: string, url: string, blurb: string, groups: array<string, list<array{name: string, url: string}>>}>, beaches: list<array{slug: string, name: string, url: string, blurb: string, groups: array<string, list<array{name: string, url: string}>>}>, lakes: list<array{slug: string, name: string, url: string, blurb: string, groups: array<string, list<array{name: string, url: string}>>}>}
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
                $menus['rivers'][] = $entry($region, route('rivers.index', $region), 'map, river strips and every spot', $rivers);
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

    /**
     * Every menu as compact JSON for the page to embed: per menu, its regions
     * with their slug, name, overview path and blurb, and their places as
     * [[heading, [[name, path], ...]], ...]. Paths are relative to keep it
     * small; nav.js builds each menu from it when it first opens.
     */
    public function menusJson(): string
    {
        $menus = [];

        foreach ($this->all() as $menu => $entries) {
            $menus[$menu] = [];

            foreach ($entries as $entry) {
                $groups = [];

                foreach ($entry['groups'] as $heading => $items) {
                    $groups[] = [(string) $heading, array_map(fn (array $item) => [$item['name'], $this->path($item['url'])], $items)];
                }

                $menus[$menu][] = ['slug' => $entry['slug'], 'name' => $entry['name'], 'url' => $this->path($entry['url']), 'blurb' => $entry['blurb'], 'groups' => $groups];
            }
        }

        return (string) json_encode($menus, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    private function path(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_PATH) ?? '/');
    }

    private function key(): string
    {
        return self::KEY_PREFIX.':'.ConditionsCache::codeVersion();
    }
}
