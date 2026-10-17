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
            'overflow_feeds' => ['northumbrian_water', 'scottish_water'],
            'bathing_water_districts' => ['Northumberland', 'North Tyneside', 'South Tyneside', 'Sunderland', 'County Durham', 'Hartlepool', 'Redcar and Cleveland'],
        ]);
        Region::query()->updateOrCreate(['slug' => 'midlands'], [
            'name' => 'Midlands',
            'overflow_feeds' => ['severn_trent'],
            'bathing_water_districts' => [],
        ]);
        // Welsh Water's overflow feed is not read yet (see CLAUDE.md), so Wales has no overflow feeds and its places say
        // overflow data is not available rather than that there are no overflows. Bathing waters come from Wales's
        // register, which names councils as the Ordnance Survey does ("the Vale of Glamorgan").
        Region::query()->updateOrCreate(['slug' => 'wales'], [
            'name' => 'Wales',
            'overflow_feeds' => [],
            'bathing_water_districts' => [
                'Isle of Anglesey', 'Gwynedd', 'Conwy', 'Denbighshire', 'Flintshire', 'Wrexham', 'Ceredigion', 'Powys',
                'Pembrokeshire', 'Carmarthenshire', 'Swansea', 'Neath Port Talbot', 'Bridgend', 'the Vale of Glamorgan',
                'Cardiff', 'Newport', 'Monmouthshire',
            ],
        ]);
        // Scottish bathing waters come from SEPA, whose reader gives each its council area ("Scotland" until one is added).
        Region::query()->updateOrCreate(['slug' => 'scotland'], [
            'name' => 'Scotland',
            'overflow_feeds' => ['scottish_water'],
            'bathing_water_districts' => [
                'Aberdeen City', 'Aberdeenshire', 'Angus', 'Argyll and Bute', 'City of Edinburgh', 'Dumfries and Galloway',
                'Dundee City', 'East Lothian', 'Fife', 'Highland', 'Inverclyde', 'Moray', 'North Ayrshire', 'Scottish Borders',
                'South Ayrshire', 'Scotland',
            ],
        ]);
    }
}
