<?php

namespace Database\Seeders;

use App\Models\River;
use Illuminate\Database\Seeder;

class RiverSeeder extends Seeder
{
    /**
     * Main rivers first, then tributaries (which reference their parent by
     * slug). Tributaries only contribute monitors; they get no pages.
     *
     * @return array<int, array{name: string, slug: string, catchment: string, aliases: array<int, string>, parent?: string}>
     */
    public static function rivers(): array
    {
        return [
            [
                'name' => 'River Wharfe',
                'slug' => 'wharfe',
                'catchment' => 'Yorkshire Ouse',
                'aliases' => ['River Wharfe'],
            ],
            [
                'name' => 'River Aire',
                'slug' => 'aire',
                'catchment' => 'Yorkshire Ouse',
                'aliases' => ['River Aire'],
            ],
            [
                'name' => 'River Worth',
                'slug' => 'worth',
                'catchment' => 'Yorkshire Ouse',
                'aliases' => ['River Worth'],
                'parent' => 'aire',
            ],
            [
                'name' => 'Bradford Beck',
                'slug' => 'bradford-beck',
                'catchment' => 'Yorkshire Ouse',
                'aliases' => ['Bradford Beck'],
                'parent' => 'aire',
            ],
            [
                'name' => 'Meanwood Beck',
                'slug' => 'meanwood-beck',
                'catchment' => 'Yorkshire Ouse',
                'aliases' => ['Meanwood Beck'],
                'parent' => 'aire',
            ],
            [
                'name' => 'Adel Beck',
                'slug' => 'adel-beck',
                'catchment' => 'Yorkshire Ouse',
                'aliases' => ['Adel Beck'],
                'parent' => 'meanwood-beck',
            ],
            [
                'name' => 'Wyke Beck',
                'slug' => 'wyke-beck',
                'catchment' => 'Yorkshire Ouse',
                'aliases' => ['Wyke Beck'],
                'parent' => 'aire',
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::rivers() as $river) {
            $parentSlug = $river['parent'] ?? null;
            unset($river['parent']);

            $river['parent_river_id'] = $parentSlug === null
                ? null
                : River::query()->where('slug', $parentSlug)->value('id');

            River::query()->updateOrCreate(['slug' => $river['slug']], $river);
        }
    }
}
