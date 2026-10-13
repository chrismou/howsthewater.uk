<?php

namespace Database\Seeders;

use App\Models\River;
use App\Models\Spot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpotSeeder extends Seeder
{
    /**
     * Spots listed source to mouth. Coordinates are the riverbank access point.
     *
     * @return array<string, array<int, array{name: string, latitude: float, longitude: float, description: string}>>
     */
    public static function spots(): array
    {
        return [
            'wharfe' => [
                ['name' => 'Kettlewell', 'latitude' => 54.0875, 'longitude' => -2.0440, 'description' => 'Upper Wharfedale village with riverside access by the bridge.'],
                ['name' => 'Linton Falls, Grassington', 'latitude' => 54.0660, 'longitude' => -2.0050, 'description' => 'Popular falls and footbridge below Grassington.'],
                ['name' => 'Burnsall', 'latitude' => 54.0455, 'longitude' => -1.9510, 'description' => 'Riverside green and bridge, busy on summer weekends.'],
                ['name' => 'Bolton Abbey', 'latitude' => 53.9970, 'longitude' => -1.8950, 'description' => 'Cavendish Pavilion and the stepping stones.'],
                ['name' => 'Addingham', 'latitude' => 53.9440, 'longitude' => -1.8810, 'description' => 'Riverside path and suspension bridge.'],
                ['name' => 'Ilkley', 'latitude' => 53.9290, 'longitude' => -1.8190, 'description' => 'Designated bathing water at Cromwheel, by the Old Bridge.'],
                ['name' => 'Burley-in-Wharfedale', 'latitude' => 53.9140, 'longitude' => -1.7540, 'description' => 'Riverside walk below the village.'],
                ['name' => 'Otley', 'latitude' => 53.9075, 'longitude' => -1.6950, 'description' => 'Wharfemeadows Park and the Otley gauge.'],
                ['name' => 'Pool-in-Wharfedale', 'latitude' => 53.9040, 'longitude' => -1.6300, 'description' => 'Pool Bridge and the riverside fields.'],
                ['name' => 'Harewood Bridge', 'latitude' => 53.9070, 'longitude' => -1.5410, 'description' => 'Below the Harewood estate on the A61.'],
                ['name' => 'Wetherby', 'latitude' => 53.9280, 'longitude' => -1.3870, 'description' => 'Wetherby weir and the riverside.'],
                ['name' => 'Boston Spa', 'latitude' => 53.9070, 'longitude' => -1.3450, 'description' => 'Riverside walk and weir.'],
                ['name' => 'Tadcaster', 'latitude' => 53.8840, 'longitude' => -1.2620, 'description' => 'Lower Wharfe, tidal below the weir.'],
            ],
            'aire' => [
                ['name' => 'Malham', 'latitude' => 54.0560, 'longitude' => -2.1540, 'description' => 'Aire Head springs just below the village.'],
                ['name' => 'Gargrave', 'latitude' => 53.9870, 'longitude' => -2.1030, 'description' => 'Village green and riverside beside the canal.'],
                ['name' => 'Skipton', 'latitude' => 53.9560, 'longitude' => -2.0120, 'description' => 'Aire south of the town centre.'],
                ['name' => 'Keighley', 'latitude' => 53.8730, 'longitude' => -1.8920, 'description' => 'Stockbridge and the riverside path.'],
                ['name' => 'Bingley', 'latitude' => 53.8500, 'longitude' => -1.8370, 'description' => 'Myrtle Park and the river below Five Rise Locks.'],
                ['name' => 'Saltaire', 'latitude' => 53.8390, 'longitude' => -1.7900, 'description' => 'Roberts Park and the boathouse.'],
                ['name' => 'Apperley Bridge', 'latitude' => 53.8390, 'longitude' => -1.7060, 'description' => 'Riverside below the marina.'],
                ['name' => 'Rodley', 'latitude' => 53.8180, 'longitude' => -1.6400, 'description' => 'Rodley Nature Reserve and the riverside path.'],
                ['name' => 'Kirkstall', 'latitude' => 53.8200, 'longitude' => -1.6060, 'description' => 'Kirkstall Abbey riverside meadows.'],
                ['name' => 'Leeds city centre', 'latitude' => 53.7930, 'longitude' => -1.5350, 'description' => 'Leeds Dock and Brewery Wharf.'],
                ['name' => 'Woodlesford', 'latitude' => 53.7570, 'longitude' => -1.4480, 'description' => 'Fleet Weir and the Aire and Calder Navigation.'],
                ['name' => 'Castleford', 'latitude' => 53.7260, 'longitude' => -1.3560, 'description' => 'Millennium Bridge below the Calder confluence.'],
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::spots() as $riverSlug => $spots) {
            $river = River::query()->where('slug', $riverSlug)->firstOrFail();

            foreach ($spots as $spot) {
                Spot::query()->updateOrCreate(
                    ['slug' => Str::slug($spot['name'])],
                    [...$spot, 'river_id' => $river->id],
                );
            }
        }
    }
}
