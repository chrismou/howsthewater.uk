<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Coastal bathing waters belong to the region whose districts list
     * theirs. Redcar and Cleveland is in the North East, not Yorkshire: its
     * beaches are Northumbrian Water's.
     */
    public function run(): void
    {
        Region::query()->updateOrCreate(['slug' => 'yorkshire'], [
            'name' => 'Yorkshire',
            'overflow_feeds' => ['yorkshire_water'],
            'bathing_water_districts' => ['North Yorkshire', 'East Riding of Yorkshire'],
        ]);
        Region::query()->updateOrCreate(['slug' => 'thames-valley'], [
            'name' => 'Thames Valley',
            'overflow_feeds' => ['thames_water'],
            'bathing_water_districts' => [],
        ]);
        Region::query()->updateOrCreate(['slug' => 'north-west'], [
            'name' => 'North West',
            'overflow_feeds' => ['united_utilities'],
            'bathing_water_districts' => ['Cumberland', 'Westmorland and Furness', 'Lancaster', 'Wyre', 'Blackpool', 'Fylde', 'Sefton', 'Wirral'],
        ]);
        Region::query()->updateOrCreate(['slug' => 'north-east'], [
            'name' => 'North East',
            'overflow_feeds' => ['northumbrian_water'],
            'bathing_water_districts' => ['Northumberland', 'North Tyneside', 'South Tyneside', 'Sunderland', 'County Durham', 'Hartlepool', 'Redcar and Cleveland'],
        ]);
    }
}
