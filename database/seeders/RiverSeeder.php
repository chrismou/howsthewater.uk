<?php

namespace Database\Seeders;

use App\Models\River;
use Illuminate\Database\Seeder;

class RiverSeeder extends Seeder
{
    /**
     * @return array<int, array{name: string, slug: string, catchment: string, aliases: array<int, string>}>
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
        ];
    }

    public function run(): void
    {
        foreach (self::rivers() as $river) {
            River::query()->updateOrCreate(['slug' => $river['slug']], $river);
        }
    }
}
