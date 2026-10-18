<?php

use App\Actions\Ingest\RefreshRegions;
use App\Models\Lake;
use App\Models\Region;
use App\Models\River;
use App\Services\Geo\WatercourseName;
use Database\Seeders\RegionSeeder;
use Database\Seeders\RiverSeeder;
use Database\Seeders\SpotSeeder;

it('seeds aliases that a feed name can match, as receiving watercourses are parsed before they are compared', function () {
    $unreachable = collect(RiverSeeder::rivers())
        ->flatMap(fn (array $rivers, string $region) => collect($rivers)->flatMap(fn (array $river) => collect($river['aliases'])
            // A river's own name has to stay as OS spells it ("The Black Bourn"), so only the extra aliases count.
            ->reject(fn (string $alias) => $alias === $river['name'])
            ->reject(fn (string $alias) => WatercourseName::parse($alias)?->name === mb_strtolower($alias))
            ->map(fn (string $alias) => "{$region}/{$river['slug']}: {$alias}")))
        ->values()
        ->all();

    expect($unreachable)->toBe([]);
});

it('seeds every parent before its tributaries and every river a main river flows into, with slugs unique in each region', function () {
    foreach (RiverSeeder::rivers() as $region => $rivers) {
        $seen = [];

        foreach ($rivers as $river) {
            expect($seen)->not->toHaveKey($river['slug'], "{$region}/{$river['slug']} is seeded twice");

            if (isset($river['parent'])) {
                expect($seen)->toHaveKey($river['parent'], "{$region}/{$river['slug']} names {$river['parent']} before it is seeded");
            }

            $seen[$river['slug']] = true;
        }

        // A main river can flow into one listed after it (the Ure into the Ouse), as those are linked once all exist.
        foreach ($rivers as $river) {
            if (isset($river['flows_into'])) {
                expect($seen)->toHaveKey($river['flows_into'], "{$region}/{$river['slug']} flows into unseeded {$river['flows_into']}");
            }
        }

        foreach (SpotSeeder::spots()[$region] ?? [] as $slug => $spots) {
            expect($seen)->toHaveKey($slug, "{$region} has spots on unseeded river {$slug}")
                ->and(collect($spots)->pluck('name')->duplicates()->all())->toBe([]);
        }
    }
});

it('gives each bathing water district to one region only', function () {
    (new RegionSeeder())->run();

    $districts = Region::query()->get()->flatMap(fn (Region $region) => $region->bathing_water_districts ?? []);

    expect($districts->duplicates()->all())->toBe([]);
});

it('names regions mid-sentence the way they are said', function () {
    $region = Region::factory()->create(['name' => 'East of England', 'slug' => 'east-of-england']);
    Lake::factory()->create(['region_id' => $region->id]);
    River::factory()->create(['region_id' => $region->id]);
    app(RefreshRegions::class)->handle([$region->id]);

    $this->get(route('lakes.index', $region))->assertOk()->assertSee('at lakes in the East of England.');
    $this->get(route('rivers.index', $region))->assertOk()->assertSee("on the East of England's rivers", false);
});
