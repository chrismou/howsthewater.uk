<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LakeSeeder extends Seeder
{
    /**
     * Lakes by region slug. Bathing water sites attach to a lake when their
     * name starts with the lake's (minus a trailing "Ponds"), or when SEPA's
     * reader names the loch (Luss Bay is on Loch Lomond). The gauge is
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
            'midlands' => [
                ['name' => 'Colwick Country Park', 'latitude' => 52.9452, 'longitude' => -1.0982, 'description' => 'A former gravel pit lake beside the Trent in Nottingham.', 'gauge_reference' => null],
            ],
            'east-of-england' => [
                ['name' => 'Rutland Water', 'latitude' => 52.6510, 'longitude' => -0.6400, 'description' => 'The large reservoir near Oakham, with bathing waters at Whitwell Creek and Sykes Lane.', 'gauge_reference' => null],
            ],
            'scotland' => [
                ['name' => 'Loch Lomond', 'latitude' => 56.0800, 'longitude' => -4.6000, 'description' => 'The largest loch by area in Britain, with the bathing water at Luss Bay.', 'gauge_reference' => null],
                ['name' => 'Loch Ness', 'latitude' => 57.3000, 'longitude' => -4.4500, 'description' => 'The long, deep loch of the Great Glen, with the bathing water at Dores.', 'gauge_reference' => null],
                ['name' => 'Loch Morlich', 'latitude' => 57.1670, 'longitude' => -3.7050, 'description' => 'The Glenmore loch below the Cairngorms, with a sandy beach.', 'gauge_reference' => null],
            ],
            'wales' => [
                ['name' => 'Llyn Padarn', 'latitude' => 53.1290, 'longitude' => -4.1250, 'description' => 'The lake below Snowdon at Llanberis, with the bathing water on its northern shore.', 'gauge_reference' => null],
                ['name' => 'Llanishen Reservoir', 'latitude' => 51.5310, 'longitude' => -3.1740, 'description' => 'The reopened reservoir in north Cardiff, used for open water swimming.', 'gauge_reference' => null],
            ],
            'london' => [
                ['name' => 'Hampstead Heath', 'latitude' => 51.5610, 'longitude' => -0.1640, 'description' => 'The Heath\'s three swimming ponds: the Ladies\', the Men\'s and the Mixed.', 'gauge_reference' => null],
                ['name' => 'The Serpentine', 'latitude' => 51.5049, 'longitude' => -0.1691, 'description' => 'The lake in Hyde Park, with a summer swimming area.', 'gauge_reference' => null],
            ],
            'wessex' => [
                ['name' => 'Henleaze Lake', 'latitude' => 51.4946, 'longitude' => -2.6057, 'description' => 'A former limestone quarry in north Bristol, swum in by its members\' club.', 'gauge_reference' => null],
            ],
            'thames-valley' => [
                ['name' => 'Cotswold Country Park', 'latitude' => 51.6610, 'longitude' => -1.9609, 'description' => 'A former gravel pit lake near Cricklade with a sand beach.', 'gauge_reference' => null],
                ['name' => 'Frensham Great Pond', 'latitude' => 51.1573, 'longitude' => -0.7918, 'description' => 'Heathland pond with a sandy shore near Farnham.', 'gauge_reference' => null],
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
