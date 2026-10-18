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
        // Wessex Water's area: Bristol, Bath, Somerset, Dorset, Wiltshire and the Hampshire Avon. Dorset's two Lyme Regis
        // beaches are South West Water's and Highcliffe is Southern Water's, but a district belongs to one region. New Forest waits for the South East, as its
        // beaches are Southern Water's.
        Region::query()->updateOrCreate(['slug' => 'wessex'], [
            'name' => 'Wessex',
            'overflow_feeds' => ['wessex_water'],
            'bathing_water_districts' => ['Dorset', 'Bournemouth, Christchurch and Poole', 'Somerset', 'North Somerset'],
        ]);
        // Thames Water's London rivers, which all join the Thames below Teddington, where the Thames Valley's stop.
        Region::query()->updateOrCreate(['slug' => 'london'], [
            'name' => 'London',
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
        // Anglian Water's area, Lincolnshire to Essex. Its beaches run from Cleethorpes to Southend, whose Thames estuary
        // sites are Anglian Water's sewerage too.
        Region::query()->updateOrCreate(['slug' => 'east-of-england'], [
            'name' => 'East of England',
            'overflow_feeds' => ['anglian_water'],
            'bathing_water_districts' => [
                'North East Lincolnshire', 'East Lindsey', "King's Lynn and West Norfolk", 'North Norfolk', 'Great Yarmouth',
                'East Suffolk', 'Tendring', 'Colchester', 'Southend-on-Sea', 'Castle Point',
            ],
        ]);
        // South West Water's area, Devon and Cornwall. Its two Lyme Regis beaches are in Dorset, whose other beaches are
        // Wessex Water's, so they are Wessex's.
        Region::query()->updateOrCreate(['slug' => 'south-west'], [
            'name' => 'South West',
            'overflow_feeds' => ['south_west_water'],
            'bathing_water_districts' => ['Cornwall', 'City of Plymouth', 'South Hams', 'Torbay', 'Teignbridge', 'East Devon', 'North Devon', 'Torridge'],
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
