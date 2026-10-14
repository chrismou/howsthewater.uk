<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LakeSeeder extends Seeder
{
    /**
     * Lakes by region slug. Bathing water sites attach to a lake when their
     * name starts with the lake's (minus a trailing "Ponds"). The gauge is
     * the EA flood monitoring station that reads the lake level.
     *
     * @return array<string, array<int, array{name: string, latitude: float, longitude: float, description: string, gauge_reference: string|null}>>
     */
    public static function lakes(): array
    {
        return [
            'north-west' => [
                ['name' => 'Windermere', 'latitude' => 54.3600, 'longitude' => -2.9400, 'description' => "England's largest natural lake.", 'gauge_reference' => '735225'],
                ['name' => 'Coniston Water', 'latitude' => 54.3400, 'longitude' => -3.0800, 'description' => 'Five miles of lake below the Old Man of Coniston.', 'gauge_reference' => '737536'],
                ['name' => 'Derwent Water', 'latitude' => 54.5800, 'longitude' => -3.1450, 'description' => 'The lake beside Keswick, fed by the River Derwent.', 'gauge_reference' => '750106'],
                ['name' => 'Ullswater', 'latitude' => 54.5800, 'longitude' => -2.8800, 'description' => 'The second largest lake in the Lake District.', 'gauge_reference' => '761595'],
                ['name' => 'Bassenthwaite Lake', 'latitude' => 54.6550, 'longitude' => -3.2200, 'description' => 'The Derwent flows through it below Derwent Water.', 'gauge_reference' => '751020'],
            ],
            'thames-valley' => [
                ['name' => 'Cotswold Country Park', 'latitude' => 51.6610, 'longitude' => -1.9609, 'description' => 'A former gravel pit lake near Cricklade with a sand beach.', 'gauge_reference' => null],
                ['name' => 'Frensham Great Pond', 'latitude' => 51.1573, 'longitude' => -0.7918, 'description' => 'Heathland pond with a sandy shore near Farnham.', 'gauge_reference' => null],
                ['name' => 'Hampstead Heath Ponds', 'latitude' => 51.5633, 'longitude' => -0.1610, 'description' => "The Ladies', Men's and Mixed bathing ponds on Hampstead Heath.", 'gauge_reference' => null],
                ['name' => 'The Serpentine', 'latitude' => 51.5049, 'longitude' => -0.1691, 'description' => 'The lake in Hyde Park, with a summer swimming area.', 'gauge_reference' => null],
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::lakes() as $regionSlug => $lakes) {
            $region = Region::query()->where('slug', $regionSlug)->firstOrFail();

            foreach ($lakes as $lake) {
                $region->lakes()->updateOrCreate(['slug' => Str::slug($lake['name'])], $lake);
            }
        }
    }
}
