<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        Region::query()->updateOrCreate(['slug' => 'yorkshire'], ['name' => 'Yorkshire', 'overflow_feeds' => ['yorkshire_water']]);
    }
}
