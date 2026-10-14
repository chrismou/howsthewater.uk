<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        Region::query()->updateOrCreate(['slug' => 'yorkshire'], ['name' => 'Yorkshire', 'overflow_feeds' => ['yorkshire_water']]);
        Region::query()->updateOrCreate(['slug' => 'thames-valley'], ['name' => 'Thames Valley', 'overflow_feeds' => ['thames_water']]);
        Region::query()->updateOrCreate(['slug' => 'north-west'], ['name' => 'North West', 'overflow_feeds' => ['united_utilities']]);
    }
}
