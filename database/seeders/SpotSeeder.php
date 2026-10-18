<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpotSeeder extends Seeder
{
    /**
     * Well known spots suggested on the home page, by slug.
     *
     * @var array<int, string>
     */
    private const FEATURED = ['ilkley', 'bolton-abbey', 'burnsall', 'otley', 'knaresborough', 'kirkby-lonsdale'];

    /**
     * Spots by region slug, then river slug, listed source to mouth.
     * Coordinates are the riverbank access point, snapped onto the OS centreline.
     *
     * @return array<string, array<string, array<int, array{name: string, latitude: float, longitude: float, description: string}>>>
     */
    public static function spots(): array
    {
        return [
            'yorkshire' => self::yorkshire(),
            'thames-valley' => self::thamesValley(),
            'london' => self::london(),
            'north-west' => self::northWest(),
            'north-east' => self::northEast(),
            'midlands' => self::midlands(),
            'east-of-england' => self::eastOfEngland(),
            'south-west' => self::southWest(),
            'wessex' => self::wessex(),
            'scotland' => self::scotland(),
            'wales' => self::wales(),
        ];
    }

    /**
     * Spots on Welsh rivers, snapped onto the OS centreline. The Wye and Dee
     * spots run on into England (Hereford to Monmouth, Chester).
     *
     * @return array<string, array<int, array{name: string, latitude: float, longitude: float, description: string}>>
     */
    private static function wales(): array
    {
        return [
            'taff' => [
                ['name' => 'Pontypridd', 'latitude' => 51.6006, 'longitude' => -3.3401, 'description' => 'Ynysangharad Park, below the old bridge.'],
                ['name' => 'Radyr Weir', 'latitude' => 51.5163, 'longitude' => -3.2422, 'description' => 'The weir and the Taff Trail north of Cardiff.'],
                ['name' => 'Blackweir', 'latitude' => 51.4915, 'longitude' => -3.1950, 'description' => 'The weir in Bute Park, Cardiff.'],
            ],
            'usk' => [
                ['name' => 'Brecon', 'latitude' => 51.9497, 'longitude' => -3.3998, 'description' => 'The Promenade above the town, by the weir.'],
                ['name' => 'Llangynidr', 'latitude' => 51.8741, 'longitude' => -3.2251, 'description' => 'The old stone bridge below the village.'],
                ['name' => 'Crickhowell', 'latitude' => 51.8554, 'longitude' => -3.1401, 'description' => 'The long bridge below the town.'],
                ['name' => 'Abergavenny', 'latitude' => 51.8170, 'longitude' => -3.0189, 'description' => 'Castle Meadows, below the town.'],
                ['name' => 'Usk', 'latitude' => 51.7015, 'longitude' => -2.9070, 'description' => 'The town bridge.'],
            ],
            'wye' => [
                ['name' => 'Rhayader', 'latitude' => 52.3024, 'longitude' => -3.5145, 'description' => 'The bridge and the falls in the town.'],
                ['name' => 'Newbridge-on-Wye', 'latitude' => 52.2153, 'longitude' => -3.4460, 'description' => 'The bridge by the village.'],
                ['name' => 'Builth Wells', 'latitude' => 52.1513, 'longitude' => -3.4041, 'description' => 'The Groe, the riverside park below the bridge.'],
                ['name' => 'Erwood', 'latitude' => 52.0822, 'longitude' => -3.3263, 'description' => 'The bridge and the rapids below the village.'],
                ['name' => 'Glasbury', 'latitude' => 52.0431, 'longitude' => -3.2010, 'description' => 'The bridge, a popular canoe launch.'],
                ['name' => 'Hay-on-Wye', 'latitude' => 52.0766, 'longitude' => -3.1369, 'description' => 'The Warren, the riverside meadow above the town.'],
                ['name' => 'Hereford', 'latitude' => 52.0507, 'longitude' => -2.7119, 'description' => 'Victoria Bridge, below the cathedral.'],
                ['name' => 'Ross-on-Wye', 'latitude' => 51.9145, 'longitude' => -2.5943, 'description' => 'The meadows below the town.'],
                ['name' => 'Symonds Yat', 'latitude' => 51.8434, 'longitude' => -2.6328, 'description' => 'The gorge and the rapids below the Yat Rock.'],
                ['name' => 'Monmouth', 'latitude' => 51.8120, 'longitude' => -2.7089, 'description' => 'The Wye bridge below the town.'],
            ],
            'tawe' => [
                ['name' => 'Craig-y-nos', 'latitude' => 51.8252, 'longitude' => -3.6826, 'description' => 'The country park below the castle.'],
                ['name' => 'Pontardawe', 'latitude' => 51.7174, 'longitude' => -3.8479, 'description' => 'The riverside park in the town.'],
            ],
            'neath' => [
                ['name' => 'Glynneath', 'latitude' => 51.7468, 'longitude' => -3.6177, 'description' => 'The river below the town, at the head of the valley.'],
            ],
            'mellte' => [
                ['name' => 'Porth yr Ogof', 'latitude' => 51.8000, 'longitude' => -3.5559, 'description' => 'The cave mouth where the Mellte goes underground.'],
                ['name' => 'Sgwd Clun-gwyn', 'latitude' => 51.7837, 'longitude' => -3.5616, 'description' => 'The first of the waterfalls below Porth yr Ogof.'],
            ],
            'towy' => [
                ['name' => 'Llandovery', 'latitude' => 51.9968, 'longitude' => -3.8059, 'description' => 'The bridge below the town.'],
                ['name' => 'Llandeilo', 'latitude' => 51.8845, 'longitude' => -3.9861, 'description' => 'The swing bridge below the town, a designated bathing water.'],
                ['name' => 'Dryslwyn', 'latitude' => 51.8618, 'longitude' => -4.1041, 'description' => 'The bridge below Dryslwyn castle.'],
            ],
            'teifi' => [
                ['name' => 'Lampeter', 'latitude' => 52.1092, 'longitude' => -4.0773, 'description' => 'The bridge below the town.'],
                ['name' => 'Llandysul', 'latitude' => 52.0393, 'longitude' => -4.3104, 'description' => 'The paddling centre and the rapids below the town.'],
                ['name' => 'Newcastle Emlyn', 'latitude' => 52.0401, 'longitude' => -4.4657, 'description' => 'The bridge below the castle.'],
                ['name' => 'Cenarth Falls', 'latitude' => 52.0453, 'longitude' => -4.5263, 'description' => 'The falls and the bridge in the village.'],
                ['name' => 'Cilgerran', 'latitude' => 52.0581, 'longitude' => -4.6341, 'description' => 'The gorge below the castle.'],
            ],
            'western-cleddau' => [
                ['name' => 'Haverfordwest', 'latitude' => 51.8080, 'longitude' => -4.9701, 'description' => 'The river above the town bridges.'],
            ],
            'dovey' => [
                ['name' => 'Dinas Mawddwy', 'latitude' => 52.7161, 'longitude' => -3.6876, 'description' => 'The river below the village.'],
                ['name' => 'Machynlleth', 'latitude' => 52.6006, 'longitude' => -3.8550, 'description' => 'Dyfi Bridge, north of the town.'],
            ],
            'mawddach' => [
                ['name' => 'Llanelltyd', 'latitude' => 52.7562, 'longitude' => -3.8988, 'description' => 'The old bridge, where the Mawddach meets the estuary.'],
            ],
            'wnion' => [
                ['name' => 'Dolgellau', 'latitude' => 52.7447, 'longitude' => -3.8868, 'description' => 'The Marian and the bridge in the town.'],
            ],
            'glaslyn' => [
                ['name' => 'Beddgelert', 'latitude' => 53.0117, 'longitude' => -4.1010, 'description' => 'Where the Colwyn joins, in the village.'],
                ['name' => 'Aberglaslyn', 'latitude' => 52.9947, 'longitude' => -4.0955, 'description' => 'The pass and the river path below the village.'],
            ],
            'conwy' => [
                ['name' => 'Fairy Glen', 'latitude' => 53.0769, 'longitude' => -3.7965, 'description' => 'The gorge above Betws-y-Coed.'],
                ['name' => 'Betws-y-Coed', 'latitude' => 53.0869, 'longitude' => -3.7963, 'description' => 'The Waterloo Bridge, below the village.'],
                ['name' => 'Llanrwst', 'latitude' => 53.1370, 'longitude' => -3.7976, 'description' => 'Pont Fawr, the old bridge in the town.'],
            ],
            'llugwy' => [
                ['name' => 'Swallow Falls', 'latitude' => 53.1012, 'longitude' => -3.8339, 'description' => 'The falls above Betws-y-Coed.'],
                ['name' => 'Miners Bridge', 'latitude' => 53.0942, 'longitude' => -3.8097, 'description' => 'The footbridge west of Betws-y-Coed.'],
            ],
            'dee' => [
                ['name' => 'Bala', 'latitude' => 52.9061, 'longitude' => -3.5875, 'description' => 'Where the Dee leaves Llyn Tegid.'],
                ['name' => 'Llandderfel', 'latitude' => 52.9187, 'longitude' => -3.5065, 'description' => 'The bridge below the village.'],
                ['name' => 'Corwen', 'latitude' => 52.9838, 'longitude' => -3.3813, 'description' => 'The bridge below the town.'],
                ['name' => 'Llangollen', 'latitude' => 52.9709, 'longitude' => -3.1697, 'description' => 'The rapids by the town bridge.'],
                ['name' => 'Bangor-on-Dee', 'latitude' => 52.9981, 'longitude' => -2.9139, 'description' => 'The old bridge in the village.'],
                ['name' => 'Chester Meadows', 'latitude' => 53.1881, 'longitude' => -2.8833, 'description' => 'The meadows above the city.'],
            ],
            'tryweryn' => [
                ['name' => 'Canolfan Tryweryn', 'latitude' => 52.9397, 'longitude' => -3.6373, 'description' => 'The national white water centre below Llyn Celyn.'],
            ],
        ];
    }

    /**
     * Spots on Scottish Water's rivers, snapped onto the OS centreline.
     *
     * @return array<string, array<int, array{name: string, latitude: float, longitude: float, description: string}>>
     */
    private static function scotland(): array
    {
        return [
            'tay' => [
                ['name' => 'Kenmore', 'latitude' => 56.5865, 'longitude' => -3.9975, 'description' => 'Where the Tay leaves Loch Tay, below the village bridge.'],
                ['name' => 'Aberfeldy', 'latitude' => 56.6247, 'longitude' => -3.8628, 'description' => 'Wade\'s Bridge and the riverside below the town.'],
                ['name' => 'Grandtully', 'latitude' => 56.6545, 'longitude' => -3.7797, 'description' => 'The rapids by the bridge, well known to paddlers.'],
                ['name' => 'Dunkeld', 'latitude' => 56.5640, 'longitude' => -3.5872, 'description' => 'Telford\'s bridge and the shingle below the cathedral.'],
                ['name' => 'Caputh', 'latitude' => 56.5397, 'longitude' => -3.4937, 'description' => 'The bridge at Caputh, between Dunkeld and Stanley.'],
                ['name' => 'Stanley', 'latitude' => 56.4786, 'longitude' => -3.4457, 'description' => 'Below Stanley Mills and the Campsie Linn rapids.'],
                ['name' => 'Perth', 'latitude' => 56.4054, 'longitude' => -3.4284, 'description' => 'The North Inch, above the town bridges.'],
            ],
            'tummel' => [
                ['name' => 'Pitlochry', 'latitude' => 56.6985, 'longitude' => -3.7323, 'description' => 'Below the dam and the Port-na-craig suspension bridge.'],
            ],
            'garry' => [
                ['name' => 'Blair Atholl', 'latitude' => 56.7627, 'longitude' => -3.8488, 'description' => 'The Garry by the village, below the falls of Bruar.'],
                ['name' => 'Killiecrankie', 'latitude' => 56.7420, 'longitude' => -3.7731, 'description' => 'The gorge below the Pass of Killiecrankie.'],
            ],
            'braan' => [
                ['name' => 'The Hermitage', 'latitude' => 56.5594, 'longitude' => -3.6092, 'description' => 'The Black Linn falls below Ossian\'s Hall.'],
            ],
            'earn' => [
                ['name' => 'Comrie', 'latitude' => 56.3735, 'longitude' => -3.9878, 'description' => 'Below the village bridge, where the Lednock joins.'],
                ['name' => 'Crieff', 'latitude' => 56.3652, 'longitude' => -3.8507, 'description' => 'Bridgend and the meadows below the town.'],
                ['name' => 'Bridge of Earn', 'latitude' => 56.3513, 'longitude' => -3.4051, 'description' => 'The old bridge piers below the village.'],
            ],
            'spey' => [
                ['name' => 'Newtonmore', 'latitude' => 57.0556, 'longitude' => -4.1179, 'description' => 'The Spey below the village, by the golf course.'],
                ['name' => 'Kingussie', 'latitude' => 57.0753, 'longitude' => -4.0475, 'description' => 'Below the town, across the Insh Marshes.'],
                ['name' => 'Aviemore', 'latitude' => 57.1892, 'longitude' => -3.8255, 'description' => 'The riverside path below the village.'],
                ['name' => 'Boat of Garten', 'latitude' => 57.2491, 'longitude' => -3.7478, 'description' => 'The bridge and the old ferry crossing.'],
                ['name' => 'Nethy Bridge', 'latitude' => 57.2814, 'longitude' => -3.6633, 'description' => 'Broomhill bridge, near Nethy Bridge.'],
                ['name' => 'Grantown-on-Spey', 'latitude' => 57.3194, 'longitude' => -3.5995, 'description' => 'The old Spey bridge and Anagach woods.'],
                ['name' => 'Aberlour', 'latitude' => 57.4730, 'longitude' => -3.2231, 'description' => 'The Alice Littler park and the footbridge.'],
                ['name' => 'Craigellachie', 'latitude' => 57.4910, 'longitude' => -3.1877, 'description' => 'Telford\'s bridge below the village.'],
                ['name' => 'Fochabers', 'latitude' => 57.6157, 'longitude' => -3.1052, 'description' => 'The Spey bridge by the village.'],
            ],
            'ness' => [
                ['name' => 'Ness Islands', 'latitude' => 57.4654, 'longitude' => -4.2308, 'description' => 'The islands and footbridges above the city.'],
                ['name' => 'Inverness', 'latitude' => 57.4791, 'longitude' => -4.2298, 'description' => 'The riverside by the castle and the Greig Street bridge.'],
            ],
            'dee' => [
                ['name' => 'Linn of Dee', 'latitude' => 56.9884, 'longitude' => -3.5452, 'description' => 'The narrow gorge and the bridge above Braemar.'],
                ['name' => 'Braemar', 'latitude' => 57.0070, 'longitude' => -3.4113, 'description' => 'The Dee below the village, by the Invercauld bridge.'],
                ['name' => 'Ballater', 'latitude' => 57.0464, 'longitude' => -3.0370, 'description' => 'The bridge and the shingle below the village.'],
                ['name' => 'Aboyne', 'latitude' => 57.0715, 'longitude' => -2.7827, 'description' => 'The bridge and the green below the village.'],
                ['name' => 'Potarch', 'latitude' => 57.0610, 'longitude' => -2.6438, 'description' => 'Potarch bridge, by the hotel.'],
                ['name' => 'Banchory', 'latitude' => 57.0477, 'longitude' => -2.4952, 'description' => 'The bridge and the riverside below the town.'],
                ['name' => 'Crathes', 'latitude' => 57.0546, 'longitude' => -2.4258, 'description' => 'Below Crathes castle.'],
                ['name' => 'Peterculter', 'latitude' => 57.0920, 'longitude' => -2.2609, 'description' => 'The riverside below the village.'],
                ['name' => 'Cults', 'latitude' => 57.1146, 'longitude' => -2.1709, 'description' => 'The Deeside Way above the city.'],
                ['name' => 'Duthie Park', 'latitude' => 57.1339, 'longitude' => -2.0960, 'description' => 'The riverside by the park in Aberdeen.'],
            ],
            'don' => [
                ['name' => 'Alford', 'latitude' => 57.2431, 'longitude' => -2.7117, 'description' => 'The bridge below the village.'],
                ['name' => 'Monymusk', 'latitude' => 57.2292, 'longitude' => -2.5147, 'description' => 'The riverside below the village.'],
                ['name' => 'Kemnay', 'latitude' => 57.2352, 'longitude' => -2.4499, 'description' => 'The Don below the village.'],
                ['name' => 'Kintore', 'latitude' => 57.2397, 'longitude' => -2.3414, 'description' => 'Below the town, by the Inverurie road.'],
                ['name' => 'Inverurie', 'latitude' => 57.2756, 'longitude' => -2.3708, 'description' => 'Where the Urie joins, below the town.'],
                ['name' => 'Dyce', 'latitude' => 57.1997, 'longitude' => -2.1718, 'description' => 'The riverside walk by Dyce.'],
                ['name' => 'Seaton Park', 'latitude' => 57.1723, 'longitude' => -2.1049, 'description' => 'The park below the cathedral in Aberdeen.'],
            ],
            'forth' => [
                ['name' => 'Aberfoyle', 'latitude' => 56.1773, 'longitude' => -4.3827, 'description' => 'The bridge below the village.'],
                ['name' => 'Stirling', 'latitude' => 56.1272, 'longitude' => -3.9356, 'description' => 'The old bridge below the castle.'],
            ],
            'teith' => [
                ['name' => 'Callander', 'latitude' => 56.2425, 'longitude' => -4.2150, 'description' => 'The meadows by the town bridge.'],
                ['name' => 'Doune', 'latitude' => 56.1864, 'longitude' => -4.0592, 'description' => 'Below Doune castle.'],
            ],
            'allan-water' => [
                ['name' => 'Dunblane', 'latitude' => 56.1869, 'longitude' => -3.9649, 'description' => 'The riverside below the cathedral.'],
                ['name' => 'Bridge of Allan', 'latitude' => 56.1543, 'longitude' => -3.9508, 'description' => 'The village bridge and the riverside walk.'],
            ],
            'devon' => [
                ['name' => 'Rumbling Bridge', 'latitude' => 56.1798, 'longitude' => -3.5854, 'description' => 'The gorge and the double bridge.'],
                ['name' => 'Dollar', 'latitude' => 56.1540, 'longitude' => -3.6723, 'description' => 'The riverside below the town.'],
                ['name' => 'Tillicoultry', 'latitude' => 56.1481, 'longitude' => -3.7386, 'description' => 'Below the town, at the Devon Way.'],
            ],
            'water-of-leith' => [
                ['name' => 'Balerno', 'latitude' => 55.8861, 'longitude' => -3.3378, 'description' => 'The walkway below the village.'],
                ['name' => 'Colinton Dell', 'latitude' => 55.9082, 'longitude' => -3.2557, 'description' => 'The wooded dell and the old railway path.'],
                ['name' => 'Dean Village', 'latitude' => 55.9520, 'longitude' => -3.2170, 'description' => 'Below the Dean Bridge, by the old mills.'],
                ['name' => 'Stockbridge', 'latitude' => 55.9576, 'longitude' => -3.2091, 'description' => 'Below the bridge and St Bernard\'s Well.'],
            ],
            'almond' => [
                ['name' => 'Livingston', 'latitude' => 55.8856, 'longitude' => -3.5190, 'description' => 'The riverside park in Livingston.'],
                ['name' => 'Almondell', 'latitude' => 55.9023, 'longitude' => -3.4628, 'description' => 'The country park by the aqueduct.'],
                ['name' => 'Cramond', 'latitude' => 55.9761, 'longitude' => -3.3024, 'description' => 'The falls and the old mill above the village.'],
            ],
            'esk' => [
                ['name' => 'Musselburgh', 'latitude' => 55.9417, 'longitude' => -3.0564, 'description' => 'The old bridge and the riverside in the town.'],
            ],
            'tyne' => [
                ['name' => 'Haddington', 'latitude' => 55.9565, 'longitude' => -2.7741, 'description' => 'The Nungate bridge below the town.'],
                ['name' => 'East Linton', 'latitude' => 55.9852, 'longitude' => -2.6545, 'description' => 'Linn Rocks and the old bridge.'],
            ],
            'clyde' => [
                ['name' => 'Falls of Clyde', 'latitude' => 55.6598, 'longitude' => -3.7807, 'description' => 'The gorge below New Lanark.'],
                ['name' => 'Kirkfieldbank', 'latitude' => 55.6759, 'longitude' => -3.8050, 'description' => 'The bridges below Lanark.'],
                ['name' => 'Crossford', 'latitude' => 55.6962, 'longitude' => -3.8670, 'description' => 'The village bridge, where the Nethan joins.'],
                ['name' => 'Strathclyde Park', 'latitude' => 55.7892, 'longitude' => -4.0327, 'description' => 'The river by the park, at Hamilton.'],
                ['name' => 'Bothwell Castle', 'latitude' => 55.8081, 'longitude' => -4.0927, 'description' => 'The bend below Bothwell Castle.'],
                ['name' => 'Cambuslang', 'latitude' => 55.8285, 'longitude' => -4.1525, 'description' => 'The Clyde Walkway at Cambuslang.'],
                ['name' => 'Glasgow Green', 'latitude' => 55.8485, 'longitude' => -4.2376, 'description' => 'Below the tidal weir in Glasgow.'],
            ],
            'kelvin' => [
                ['name' => 'Kirkintilloch', 'latitude' => 55.9433, 'longitude' => -4.1628, 'description' => 'The riverside below the town.'],
                ['name' => 'Dawsholm', 'latitude' => 55.8928, 'longitude' => -4.3028, 'description' => 'Dawsholm park and the Kelvin Walkway.'],
                ['name' => 'Kelvingrove', 'latitude' => 55.8691, 'longitude' => -4.2856, 'description' => 'The river below Kelvingrove park.'],
            ],
            'white-cart-water' => [
                ['name' => 'Linn Park', 'latitude' => 55.8020, 'longitude' => -4.2644, 'description' => 'The waterfalls in Linn Park.'],
                ['name' => 'Pollok Country Park', 'latitude' => 55.8261, 'longitude' => -4.3129, 'description' => 'The weir by Pollok House.'],
            ],
            'leven' => [
                ['name' => 'Balloch', 'latitude' => 56.0030, 'longitude' => -4.5817, 'description' => 'Where the Leven leaves Loch Lomond.'],
            ],
            'irvine' => [
                ['name' => 'Galston', 'latitude' => 55.6030, 'longitude' => -4.3844, 'description' => 'The riverside below the town.'],
                ['name' => 'Irvine', 'latitude' => 55.6133, 'longitude' => -4.6700, 'description' => 'The riverside by the town centre.'],
            ],
            'ayr' => [
                ['name' => 'Catrine', 'latitude' => 55.5031, 'longitude' => -4.3310, 'description' => 'Below the village weir.'],
                ['name' => 'Ayr', 'latitude' => 55.4624, 'longitude' => -4.6264, 'description' => 'The Auld Brig in the town.'],
            ],
            'doon' => [
                ['name' => 'Brig o\' Doon', 'latitude' => 55.4279, 'longitude' => -4.6394, 'description' => 'The old bridge at Alloway.'],
            ],
            'nith' => [
                ['name' => 'Sanquhar', 'latitude' => 55.3636, 'longitude' => -3.9282, 'description' => 'The riverside below the town.'],
                ['name' => 'Thornhill', 'latitude' => 55.2403, 'longitude' => -3.7776, 'description' => 'The bridge below the village.'],
                ['name' => 'Dumfries', 'latitude' => 55.0675, 'longitude' => -3.6148, 'description' => 'The Whitesands by the caul.'],
            ],
            'annan' => [
                ['name' => 'Moffat', 'latitude' => 55.3276, 'longitude' => -3.4439, 'description' => 'The riverside below the town.'],
                ['name' => 'Annan', 'latitude' => 54.9888, 'longitude' => -3.2657, 'description' => 'The bridge in the town.'],
            ],
            'findhorn' => [
                ['name' => 'Dulsie Bridge', 'latitude' => 57.4376, 'longitude' => -3.8331, 'description' => 'The gorge below the old bridge.'],
                ['name' => 'Randolph\'s Leap', 'latitude' => 57.5194, 'longitude' => -3.6844, 'description' => 'The narrow gorge and pools near Logie.'],
                ['name' => 'Forres', 'latitude' => 57.6143, 'longitude' => -3.6329, 'description' => 'The Findhorn bridge below the town.'],
            ],
            'lossie' => [
                ['name' => 'Elgin', 'latitude' => 57.6526, 'longitude' => -3.3149, 'description' => 'The riverside below Cooper Park.'],
            ],
            'eden' => [
                ['name' => 'Cupar', 'latitude' => 56.3190, 'longitude' => -3.0085, 'description' => 'The riverside in the town.'],
                ['name' => 'Guardbridge', 'latitude' => 56.3616, 'longitude' => -2.8887, 'description' => 'The old bridge at the head of the estuary.'],
            ],
            'south-esk' => [
                ['name' => 'Brechin', 'latitude' => 56.7262, 'longitude' => -2.6547, 'description' => 'The bridge below Brechin castle.'],
                ['name' => 'Bridge of Dun', 'latitude' => 56.7163, 'longitude' => -2.5499, 'description' => 'The old bridge above Montrose Basin.'],
            ],
            'north-esk' => [
                ['name' => 'Gannochy Bridge', 'latitude' => 56.8163, 'longitude' => -2.6498, 'description' => 'The Rocks of Solitude gorge above Edzell.'],
                ['name' => 'Marykirk', 'latitude' => 56.7817, 'longitude' => -2.5214, 'description' => 'The bridge below the village.'],
            ],
            'dee-galloway' => [
                ['name' => 'Threave', 'latitude' => 54.9385, 'longitude' => -3.9698, 'description' => 'The river around Threave castle island.'],
                ['name' => 'Tongland', 'latitude' => 54.8579, 'longitude' => -4.0383, 'description' => 'Below the bridge at Tongland.'],
            ],
        ];
    }

    /**
     * Spots on Wessex Water's rivers, snapped onto the OS centreline. The river bathing waters (Farleigh Hungerford,
     * French Weir, Queen Elizabeth Gardens and Fordingbridge) each have a spot beside them. Queen Elizabeth Gardens
     * and Keynsham sit just below the Nadder and Chew, so those rivers count upstream.
     *
     * @return array<string, array<int, array{name: string, latitude: float, longitude: float, description: string}>>
     */
    private static function wessex(): array
    {
        return [
            'bristol-avon' => [
                ['name' => 'Malmesbury', 'latitude' => 51.5856, 'longitude' => -2.0973, 'description' => 'The river looping round the hilltop town.'],
                ['name' => 'Chippenham', 'latitude' => 51.4596, 'longitude' => -2.1167, 'description' => 'Monkton Park and the river through the town.'],
                ['name' => 'Lacock', 'latitude' => 51.4144, 'longitude' => -2.1155, 'description' => 'The river by the National Trust village.'],
                ['name' => 'Bradford-on-Avon', 'latitude' => 51.3445, 'longitude' => -2.2556, 'description' => 'The town bridge with its old lock-up.'],
                ['name' => 'Avoncliff', 'latitude' => 51.3392, 'longitude' => -2.2816, 'description' => 'The aqueduct carrying the canal over the river.'],
                ['name' => 'Warleigh Weir', 'latitude' => 51.3786, 'longitude' => -2.2998, 'description' => 'The weir and meadow above Bath.'],
                ['name' => 'Bath', 'latitude' => 51.3799, 'longitude' => -2.3549, 'description' => 'The river through the city centre.'],
                ['name' => 'Saltford', 'latitude' => 51.4062, 'longitude' => -2.4491, 'description' => 'The lock and weir at Saltford.'],
                ['name' => 'Keynsham', 'latitude' => 51.4202, 'longitude' => -2.4926, 'description' => 'Where the Chew joins the Avon.'],
                ['name' => 'Conham River Park', 'latitude' => 51.4451, 'longitude' => -2.5330, 'description' => 'The wooded riverside park at Hanham.'],
            ],
            'somerset-frome' => [
                ['name' => 'Frome', 'latitude' => 51.2328, 'longitude' => -2.3216, 'description' => 'The river through the town.'],
                ['name' => 'Farleigh Hungerford', 'latitude' => 51.3179, 'longitude' => -2.2809, 'description' => 'The river swimming club\'s meadow near the castle.'],
            ],
            'tone' => [
                ['name' => 'Bradford on Tone', 'latitude' => 50.9995, 'longitude' => -3.1844, 'description' => 'The village bridge over the Tone.'],
                ['name' => 'French Weir', 'latitude' => 51.0181, 'longitude' => -3.1129, 'description' => 'The park and weir in Taunton.'],
            ],
            'parrett' => [
                ['name' => 'Langport', 'latitude' => 51.0354, 'longitude' => -2.8293, 'description' => 'The old river port on the Levels.'],
                ['name' => 'Burrowbridge', 'latitude' => 51.0689, 'longitude' => -2.9177, 'description' => 'Below Burrow Mump, where the Tone joins the Parrett.'],
            ],
            'brue' => [
                ['name' => 'Bruton', 'latitude' => 51.1119, 'longitude' => -2.4529, 'description' => 'The river through the town below the dovecote.'],
                ['name' => 'Glastonbury', 'latitude' => 51.1355, 'longitude' => -2.7163, 'description' => 'Pomparles Bridge, on the Levels below the Tor.'],
            ],
            'stour' => [
                ['name' => 'Sturminster Newton', 'latitude' => 50.9222, 'longitude' => -2.3056, 'description' => 'The mill and the old town bridge.'],
                ['name' => 'Blandford Forum', 'latitude' => 50.8534, 'longitude' => -2.1687, 'description' => 'The meadows below the Georgian town.'],
                ['name' => 'Wimborne Minster', 'latitude' => 50.7946, 'longitude' => -1.9919, 'description' => 'The river by the minster town.'],
                ['name' => 'Tuckton', 'latitude' => 50.7312, 'longitude' => -1.7944, 'description' => 'The bridge and tea gardens near Christchurch.'],
            ],
            'dorset-frome' => [
                ['name' => 'Maiden Newton', 'latitude' => 50.7767, 'longitude' => -2.5735, 'description' => 'The river through the village.'],
                ['name' => 'Dorchester', 'latitude' => 50.7185, 'longitude' => -2.4296, 'description' => 'Grey\'s Bridge and the water meadows.'],
                ['name' => 'Wool', 'latitude' => 50.6833, 'longitude' => -2.2221, 'description' => 'The old bridge by Woolbridge Manor.'],
                ['name' => 'Wareham', 'latitude' => 50.6840, 'longitude' => -2.1102, 'description' => 'The quay on the edge of Poole Harbour.'],
            ],
            'piddle' => [
                ['name' => 'Puddletown', 'latitude' => 50.7502, 'longitude' => -2.3446, 'description' => 'The village in the Piddle valley.'],
                ['name' => 'Tolpuddle', 'latitude' => 50.7482, 'longitude' => -2.2942, 'description' => 'The village of the Tolpuddle Martyrs.'],
            ],
            'hampshire-avon' => [
                ['name' => 'Upavon', 'latitude' => 51.2935, 'longitude' => -1.8058, 'description' => 'The young river in the Vale of Pewsey.'],
                ['name' => 'Amesbury', 'latitude' => 51.1716, 'longitude' => -1.7853, 'description' => 'The river near Stonehenge.'],
                ['name' => 'Queen Elizabeth Gardens', 'latitude' => 51.0648, 'longitude' => -1.8021, 'description' => 'The gardens in Salisbury with the view of the cathedral, just below where the Nadder joins.'],
                ['name' => 'Downton', 'latitude' => 50.9917, 'longitude' => -1.7464, 'description' => 'The river through the village.'],
                ['name' => 'Fordingbridge', 'latitude' => 50.9264, 'longitude' => -1.7888, 'description' => 'The recreation ground by the medieval bridge.'],
                ['name' => 'Ringwood', 'latitude' => 50.8485, 'longitude' => -1.7963, 'description' => 'The water meadows by the town.'],
                ['name' => 'Christchurch', 'latitude' => 50.7333, 'longitude' => -1.7729, 'description' => 'The quay where the Avon meets the harbour.'],
            ],
        ];
    }

    /**
     * Spots on Thames Water's London rivers, snapped onto the OS centreline.
     *
     * @return array<string, array<int, array{name: string, latitude: float, longitude: float, description: string}>>
     */
    private static function london(): array
    {
        return [
            'lea' => [
                ['name' => 'Wheathampstead', 'latitude' => 51.8135, 'longitude' => -0.2924, 'description' => 'The Lea through the village meadows.'],
                ['name' => 'Hartham Common', 'latitude' => 51.7999, 'longitude' => -0.0764, 'description' => 'The common in Hertford where the Beane joins the Lea.'],
                ['name' => 'Ware', 'latitude' => 51.8098, 'longitude' => -0.0301, 'description' => 'The riverside gazebos in Ware.'],
                ['name' => 'Broxbourne', 'latitude' => 51.7430, 'longitude' => -0.0124, 'description' => 'The meadows and old mill in the Lee Valley.'],
                ['name' => 'Tottenham Marshes', 'latitude' => 51.6002, 'longitude' => -0.0460, 'description' => 'Open marshland in the Lee Valley.'],
                ['name' => 'Hackney Marshes', 'latitude' => 51.5543, 'longitude' => -0.0252, 'description' => 'The marshes and playing fields beside the Lea.'],
            ],
            'roding' => [
                ['name' => 'Ongar', 'latitude' => 51.7064, 'longitude' => 0.2564, 'description' => 'The water meadows by Chipping Ongar.'],
                ['name' => 'Abridge', 'latitude' => 51.6518, 'longitude' => 0.1192, 'description' => 'The village bridge over the Roding.'],
                ['name' => 'Roding Valley Meadows', 'latitude' => 51.6388, 'longitude' => 0.0772, 'description' => 'Hay meadows between Loughton and Chigwell.'],
                ['name' => 'Ilford', 'latitude' => 51.5573, 'longitude' => 0.0654, 'description' => 'By the bridge at Ilford.'],
            ],
            'brent' => [
                ['name' => 'Tokyngton Park', 'latitude' => 51.5435, 'longitude' => -0.2750, 'description' => 'The restored river through the park.'],
                ['name' => 'Brent Lodge Park', 'latitude' => 51.5111, 'longitude' => -0.3439, 'description' => 'The wooded valley by the Wharncliffe Viaduct.'],
            ],
            'crane' => [
                ['name' => 'Cranford Park', 'latitude' => 51.4947, 'longitude' => -0.4115, 'description' => 'The parkland at Cranford.'],
                ['name' => 'Crane Park', 'latitude' => 51.4445, 'longitude' => -0.3801, 'description' => 'The island nature reserve by the Shot Tower.'],
            ],
            'wandle' => [
                ['name' => 'Morden Hall Park', 'latitude' => 51.4022, 'longitude' => -0.1876, 'description' => 'The park and its old snuff mills.'],
                ['name' => 'Merton Abbey Mills', 'latitude' => 51.4110, 'longitude' => -0.1842, 'description' => 'The old textile mills by the river.'],
                ['name' => 'King George\'s Park', 'latitude' => 51.4476, 'longitude' => -0.1932, 'description' => 'The park in the Wandle valley at Earlsfield.'],
            ],
            'beverley-brook' => [
                ['name' => 'Richmond Park', 'latitude' => 51.4486, 'longitude' => -0.2550, 'description' => 'The brook along the edge of Richmond Park.'],
                ['name' => 'Barn Elms', 'latitude' => 51.4714, 'longitude' => -0.2270, 'description' => 'The playing fields where the brook meets the Thames.'],
            ],
            'ravensbourne' => [
                ['name' => 'Ladywell Fields', 'latitude' => 51.4565, 'longitude' => -0.0180, 'description' => 'The restored river in the park at Ladywell.'],
            ],
            'darent' => [
                ['name' => 'Otford', 'latitude' => 51.3129, 'longitude' => 0.1845, 'description' => 'The river through the village.'],
                ['name' => 'Shoreham', 'latitude' => 51.3335, 'longitude' => 0.1811, 'description' => 'The village beneath the chalk cross.'],
                ['name' => 'Lullingstone', 'latitude' => 51.3636, 'longitude' => 0.1971, 'description' => 'The country park by the Roman villa.'],
                ['name' => 'Eynsford', 'latitude' => 51.3688, 'longitude' => 0.2125, 'description' => 'The ford and the packhorse bridge.'],
                ['name' => 'Farningham', 'latitude' => 51.3801, 'longitude' => 0.2190, 'description' => 'The river below the old mill.'],
                ['name' => 'Dartford', 'latitude' => 51.4431, 'longitude' => 0.2202, 'description' => 'The river through Central Park.'],
            ],
        ];
    }

    /**
     * Spots on South West Water's rivers, snapped onto the OS centreline.
     *
     * @return array<string, array<int, array{name: string, latitude: float, longitude: float, description: string}>>
     */
    private static function southWest(): array
    {
        return [
            'east-dart' => [
                ['name' => 'Postbridge', 'latitude' => 50.5931, 'longitude' => -3.9104, 'description' => 'The clapper bridge in the middle of Dartmoor.'],
                ['name' => 'Bellever', 'latitude' => 50.5764, 'longitude' => -3.8982, 'description' => 'The old bridge and forest below Bellever Tor.'],
            ],
            'dart' => [
                ['name' => 'Dartmeet', 'latitude' => 50.5410, 'longitude' => -3.8750, 'description' => 'Where the East and West Dart meet.'],
                ['name' => 'Spitchwick', 'latitude' => 50.5294, 'longitude' => -3.8149, 'description' => 'The common and pools by New Bridge.'],
                ['name' => 'Holne Bridge', 'latitude' => 50.5215, 'longitude' => -3.7994, 'description' => 'The wooded valley by the old bridge.'],
                ['name' => 'Buckfast', 'latitude' => 50.4871, 'longitude' => -3.7717, 'description' => 'The river below the abbey.'],
                ['name' => 'Staverton', 'latitude' => 50.4617, 'longitude' => -3.7086, 'description' => 'The old bridge and the steam railway.'],
                ['name' => 'Totnes', 'latitude' => 50.4317, 'longitude' => -3.6814, 'description' => 'The town at the head of the estuary.'],
            ],
            'teign' => [
                ['name' => 'Chagford', 'latitude' => 50.6797, 'longitude' => -3.8387, 'description' => 'The meadows by Chagford Bridge.'],
                ['name' => 'Fingle Bridge', 'latitude' => 50.6949, 'longitude' => -3.7838, 'description' => 'The packhorse bridge in the Teign Gorge.'],
            ],
            'plym' => [
                ['name' => 'Shaugh Bridge', 'latitude' => 50.4541, 'longitude' => -4.0693, 'description' => 'Where the Meavy joins, below the Dewerstone.'],
                ['name' => 'Plymbridge', 'latitude' => 50.4159, 'longitude' => -4.0808, 'description' => 'The woods on the edge of Plymouth.'],
            ],
            'erme' => [
                ['name' => 'Ivybridge', 'latitude' => 50.3891, 'longitude' => -3.9201, 'description' => 'The river through the town.'],
            ],
            'avon' => [
                ['name' => 'Shipley Bridge', 'latitude' => 50.4442, 'longitude' => -3.8578, 'description' => 'The moorland valley below the Avon Dam.'],
                ['name' => 'Avonwick', 'latitude' => 50.4125, 'longitude' => -3.8188, 'description' => 'The bridge by the village.'],
                ['name' => 'Aveton Gifford', 'latitude' => 50.3086, 'longitude' => -3.8395, 'description' => 'The tidal causeway at the head of the estuary.'],
            ],
            'barle' => [
                ['name' => 'Withypool', 'latitude' => 51.1066, 'longitude' => -3.6505, 'description' => 'The bridge by the Exmoor village.'],
                ['name' => 'Tarr Steps', 'latitude' => 51.0776, 'longitude' => -3.6183, 'description' => 'The ancient clapper bridge in the woods.'],
                ['name' => 'Dulverton', 'latitude' => 51.0387, 'longitude' => -3.5528, 'description' => 'The bridge below the town.'],
            ],
            'exe' => [
                ['name' => 'Tiverton', 'latitude' => 50.8966, 'longitude' => -3.4907, 'description' => 'The riverside in the town, below where the Lowman joins.'],
                ['name' => 'Bickleigh', 'latitude' => 50.8510, 'longitude' => -3.5111, 'description' => 'The bridge and mill by the village.'],
                ['name' => 'Exeter Quay', 'latitude' => 50.7179, 'longitude' => -3.5301, 'description' => 'The historic quay in the city.'],
            ],
            'tamar' => [
                ['name' => 'Gunnislake', 'latitude' => 50.5158, 'longitude' => -4.2089, 'description' => 'Weir Head, where the river turns tidal.'],
            ],
            'tavy' => [
                ['name' => 'Tavistock', 'latitude' => 50.5485, 'longitude' => -4.1441, 'description' => 'The meadows through the town.'],
            ],
            'fowey' => [
                ['name' => 'Golitha Falls', 'latitude' => 50.4888, 'longitude' => -4.5097, 'description' => 'The wooded cascades below Bodmin Moor.'],
                ['name' => 'Lostwithiel', 'latitude' => 50.4061, 'longitude' => -4.6683, 'description' => 'The medieval bridge at the head of the estuary.'],
            ],
            'camel' => [
                ['name' => 'Dunmere', 'latitude' => 50.4731, 'longitude' => -4.7595, 'description' => 'The Camel Trail below Bodmin.'],
                ['name' => 'Wadebridge', 'latitude' => 50.5174, 'longitude' => -4.8343, 'description' => 'The old bridge at the head of the estuary.'],
            ],
            'taw' => [
                ['name' => 'Umberleigh', 'latitude' => 50.9945, 'longitude' => -3.9855, 'description' => 'The bridge by the village.'],
            ],
            'torridge' => [
                ['name' => 'Torrington', 'latitude' => 50.9488, 'longitude' => -4.1441, 'description' => 'Taddiport bridge below the town.'],
            ],
            'east-lyn' => [
                ['name' => 'Watersmeet', 'latitude' => 51.2243, 'longitude' => -3.7992, 'description' => 'Where Hoaroak Water joins, in the wooded gorge.'],
                ['name' => 'Lynmouth', 'latitude' => 51.2304, 'longitude' => -3.8297, 'description' => 'The village where the river meets the sea.'],
            ],
            'otter' => [
                ['name' => 'Ottery St Mary', 'latitude' => 50.7508, 'longitude' => -3.2854, 'description' => 'The riverside below the town.'],
                ['name' => 'Otterton', 'latitude' => 50.6622, 'longitude' => -3.3021, 'description' => 'The mill and the riverside path to Budleigh.'],
            ],
            'axe' => [
                ['name' => 'Axminster', 'latitude' => 50.7815, 'longitude' => -3.0031, 'description' => 'The riverside below the town.'],
            ],
        ];
    }

    /**
     * Spots on Anglian Water's rivers, snapped onto the OS centreline.
     *
     * @return array<string, array<int, array{name: string, latitude: float, longitude: float, description: string}>>
     */
    private static function eastOfEngland(): array
    {
        return [
            'great-ouse' => [
                ['name' => 'Olney', 'latitude' => 52.1490, 'longitude' => -0.7015, 'description' => 'The riverside recreation ground below the town.'],
                ['name' => 'Bedford Embankment', 'latitude' => 52.1345, 'longitude' => -0.4622, 'description' => 'The Embankment gardens by the town bridge.'],
                ['name' => 'St Neots', 'latitude' => 52.2283, 'longitude' => -0.2729, 'description' => 'Riverside Park, by the town bridge.'],
                ['name' => 'Houghton Mill', 'latitude' => 52.3305, 'longitude' => -0.1215, 'description' => 'The National Trust watermill and its meadow.'],
                ['name' => 'Hemingford Meadow', 'latitude' => 52.3231, 'longitude' => -0.1011, 'description' => 'The meadow between the Hemingfords and St Ives.'],
                ['name' => 'St Ives', 'latitude' => 52.3223, 'longitude' => -0.0749, 'description' => 'The quay by the medieval bridge chapel.'],
                ['name' => 'Ely', 'latitude' => 52.3954, 'longitude' => 0.2702, 'description' => 'The riverside below the cathedral.'],
            ],
            'cam' => [
                ['name' => 'Byron\'s Pool', 'latitude' => 52.1713, 'longitude' => 0.0993, 'description' => 'The nature reserve pool above Grantchester.'],
                ['name' => 'Grantchester Meadows', 'latitude' => 52.1865, 'longitude' => 0.1020, 'description' => 'The meadows between Grantchester and Newnham.'],
                ['name' => 'Sheep\'s Green', 'latitude' => 52.1957, 'longitude' => 0.1166, 'description' => 'Designated bathing water by the Newnham riverside.'],
                ['name' => 'Clayhithe', 'latitude' => 52.2645, 'longitude' => 0.2075, 'description' => 'The bridge and towpath below Waterbeach.'],
            ],
            'lark' => [
                ['name' => 'Mildenhall', 'latitude' => 52.3406, 'longitude' => 0.5114, 'description' => 'The riverside walk below the town.'],
                ['name' => 'Isleham', 'latitude' => 52.3520, 'longitude' => 0.4207, 'description' => 'The lock and marina north of the village.'],
            ],
            'little-ouse' => [
                ['name' => 'Thetford', 'latitude' => 52.4141, 'longitude' => 0.7451, 'description' => 'The riverside in the town, below where the Thet joins.'],
                ['name' => 'Santon Downham', 'latitude' => 52.4587, 'longitude' => 0.6731, 'description' => 'The bridge in Thetford Forest.'],
                ['name' => 'Brandon', 'latitude' => 52.4498, 'longitude' => 0.6195, 'description' => 'The bridge and riverside below the town.'],
            ],
            'nene' => [
                ['name' => 'Thrapston', 'latitude' => 52.3944, 'longitude' => -0.5434, 'description' => 'The medieval bridge between Thrapston and Islip.'],
                ['name' => 'Wadenhoe', 'latitude' => 52.4376, 'longitude' => -0.5117, 'description' => 'The mill and meadows below the village.'],
                ['name' => 'Oundle', 'latitude' => 52.4780, 'longitude' => -0.4718, 'description' => 'The riverside below the town.'],
                ['name' => 'Fotheringhay', 'latitude' => 52.5242, 'longitude' => -0.4402, 'description' => 'The bridge below the church and castle mound.'],
                ['name' => 'Wansford', 'latitude' => 52.5778, 'longitude' => -0.4155, 'description' => 'The old bridge by the village.'],
                ['name' => 'Peterborough Embankment', 'latitude' => 52.5682, 'longitude' => -0.2400, 'description' => 'The Embankment in the city centre.'],
            ],
            'welland' => [
                ['name' => 'Duddington', 'latitude' => 52.5949, 'longitude' => -0.5426, 'description' => 'The mill and bridge by the village.'],
                ['name' => 'Stamford Meadows', 'latitude' => 52.6496, 'longitude' => -0.4808, 'description' => 'The town meadows below the old bridge.'],
                ['name' => 'Market Deeping', 'latitude' => 52.6748, 'longitude' => -0.3182, 'description' => 'The riverside through the town.'],
            ],
            'witham' => [
                ['name' => 'Lincoln', 'latitude' => 53.2280, 'longitude' => -0.5433, 'description' => 'The river through the city centre.'],
                ['name' => 'Kirkstead', 'latitude' => 53.1393, 'longitude' => -0.2404, 'description' => 'The bridge between Woodhall Spa and Martin.'],
            ],
            'wensum' => [
                ['name' => 'Lyng', 'latitude' => 52.7184, 'longitude' => 1.0659, 'description' => 'The mill and bridge by the village.'],
                ['name' => 'Drayton', 'latitude' => 52.6727, 'longitude' => 1.2181, 'description' => 'The riverside on the edge of Norwich.'],
                ['name' => 'Norwich', 'latitude' => 52.6332, 'longitude' => 1.2984, 'description' => 'The river through the city centre.'],
            ],
            'yare' => [
                ['name' => 'Bawburgh', 'latitude' => 52.6333, 'longitude' => 1.1845, 'description' => 'The bridge and mill by the village.'],
                ['name' => 'Brundall', 'latitude' => 52.6181, 'longitude' => 1.4299, 'description' => 'The riverside and boatyards below the village.'],
            ],
            'bure' => [
                ['name' => 'Aylsham', 'latitude' => 52.8000, 'longitude' => 1.2587, 'description' => 'The mill and old navigation head by the town.'],
                ['name' => 'Coltishall', 'latitude' => 52.7300, 'longitude' => 1.3551, 'description' => 'The common and staithe by the village.'],
                ['name' => 'Wroxham', 'latitude' => 52.7132, 'longitude' => 1.4070, 'description' => 'The bridge between Wroxham and Hoveton.'],
                ['name' => 'Horning', 'latitude' => 52.7031, 'longitude' => 1.4630, 'description' => 'The staithe in the village.'],
            ],
            'waveney' => [
                ['name' => 'Hoxne', 'latitude' => 52.3573, 'longitude' => 1.2060, 'description' => 'The riverside below the village.'],
                ['name' => 'Homersfield', 'latitude' => 52.4202, 'longitude' => 1.3565, 'description' => 'The old concrete bridge by the village.'],
                ['name' => 'Bungay', 'latitude' => 52.4566, 'longitude' => 1.4413, 'description' => 'Falcon Meadow and Outney Common, with a designated bathing water.'],
                ['name' => 'Geldeston', 'latitude' => 52.4634, 'longitude' => 1.5144, 'description' => 'The old lock below the village.'],
                ['name' => 'Beccles', 'latitude' => 52.4615, 'longitude' => 1.5626, 'description' => 'The quay below the town.'],
            ],
            'stour' => [
                ['name' => 'Clare', 'latitude' => 52.0760, 'longitude' => 0.5810, 'description' => 'The country park below the castle.'],
                ['name' => 'Sudbury', 'latitude' => 52.0361, 'longitude' => 0.7213, 'description' => 'Friars Meadow, a designated bathing water.'],
                ['name' => 'Nayland', 'latitude' => 51.9694, 'longitude' => 0.8704, 'description' => 'The bridge by the village.'],
                ['name' => 'Dedham', 'latitude' => 51.9614, 'longitude' => 0.9924, 'description' => 'The bridge and boatyard by the village.'],
                ['name' => 'Flatford', 'latitude' => 51.9574, 'longitude' => 1.0219, 'description' => 'The mill and lock painted by Constable.'],
            ],
            'gipping' => [
                ['name' => 'Needham Market', 'latitude' => 52.1548, 'longitude' => 1.0543, 'description' => 'Needham Lake and the riverside path.'],
            ],
            'deben' => [
                ['name' => 'Wickham Market', 'latitude' => 52.1557, 'longitude' => 1.3699, 'description' => 'The riverside below the town.'],
                ['name' => 'Woodbridge', 'latitude' => 52.0936, 'longitude' => 1.3251, 'description' => 'The tidal quay by the Tide Mill.'],
            ],
            'alde' => [
                ['name' => 'Snape Maltings', 'latitude' => 52.1651, 'longitude' => 1.4946, 'description' => 'The tidal river by the concert hall.'],
            ],
            'colne' => [
                ['name' => 'Colchester', 'latitude' => 51.8941, 'longitude' => 0.9029, 'description' => 'Castle Park and the riverside below the town.'],
            ],
            'blackwater' => [
                ['name' => 'Coggeshall', 'latitude' => 51.8696, 'longitude' => 0.6901, 'description' => 'The abbey mill and riverside by the town.'],
            ],
            'crouch' => [
                ['name' => 'Battlesbridge', 'latitude' => 51.6226, 'longitude' => 0.5663, 'description' => 'The old tide mill village at the head of the estuary.'],
            ],
            'chelmer' => [
                ['name' => 'Chelmsford', 'latitude' => 51.7292, 'longitude' => 0.4812, 'description' => 'The riverside in the city, below where the Can joins.'],
                ['name' => 'Ulting', 'latitude' => 51.7427, 'longitude' => 0.6134, 'description' => 'The navigation and towpath by the riverside church.'],
            ],
        ];
    }

    /**
     * Spots on Severn Trent's rivers, snapped onto the OS centreline.
     *
     * @return array<string, array<int, array{name: string, latitude: float, longitude: float, description: string}>>
     */
    private static function midlands(): array
    {
        return [
            'derwent' => [
                ['name' => 'Hathersage', 'latitude' => 53.3218, 'longitude' => -1.6506, 'description' => 'Leadmill Bridge and the riverside below the village.'],
                ['name' => 'Grindleford', 'latitude' => 53.3022, 'longitude' => -1.6377, 'description' => 'Grindleford bridge, at the foot of Padley Gorge.'],
                ['name' => 'Froggatt', 'latitude' => 53.2908, 'longitude' => -1.6367, 'description' => 'The old bridge and the meadows below Froggatt Edge.'],
                ['name' => 'Calver', 'latitude' => 53.2702, 'longitude' => -1.6298, 'description' => 'Calver bridge and the weir by the mill.'],
                ['name' => 'Chatsworth', 'latitude' => 53.2264, 'longitude' => -1.6144, 'description' => 'The park reach below Chatsworth House.'],
                ['name' => 'Rowsley', 'latitude' => 53.1894, 'longitude' => -1.6166, 'description' => 'Where the Wye joins, by the village bridge.'],
                ['name' => 'Matlock', 'latitude' => 53.1379, 'longitude' => -1.5562, 'description' => 'Hall Leys Park and the town bridge.'],
                ['name' => 'Matlock Bath', 'latitude' => 53.1215, 'longitude' => -1.5600, 'description' => 'The gorge below High Tor.'],
                ['name' => 'Cromford', 'latitude' => 53.1106, 'longitude' => -1.5546, 'description' => 'Cromford bridge, by the mills.'],
                ['name' => 'Whatstandwell', 'latitude' => 53.0840, 'longitude' => -1.5069, 'description' => 'Whatstandwell bridge and the canal towpath.'],
                ['name' => 'Belper', 'latitude' => 53.0211, 'longitude' => -1.4892, 'description' => 'River Gardens below the North Mill.'],
                ['name' => 'Duffield', 'latitude' => 52.9896, 'longitude' => -1.4853, 'description' => 'Duffield bridge, where the Ecclesbourne joins.'],
                ['name' => 'Darley Abbey', 'latitude' => 52.9400, 'longitude' => -1.4737, 'description' => 'The weir and mills above Derby.'],
            ],
            'wye' => [
                ['name' => 'Chee Dale', 'latitude' => 53.2570, 'longitude' => -1.8065, 'description' => 'Stepping stones under the limestone cliffs.'],
                ['name' => 'Millers Dale', 'latitude' => 53.2560, 'longitude' => -1.7916, 'description' => 'Below the viaducts on the Monsal Trail.'],
                ['name' => 'Monsal Dale', 'latitude' => 53.2389, 'longitude' => -1.7331, 'description' => 'The weir below Monsal Head viaduct.'],
                ['name' => 'Ashford in the Water', 'latitude' => 53.2234, 'longitude' => -1.7093, 'description' => 'Sheepwash Bridge and the village.'],
                ['name' => 'Bakewell', 'latitude' => 53.2145, 'longitude' => -1.6728, 'description' => 'The medieval bridge and riverside meadows.'],
                ['name' => 'Haddon Hall', 'latitude' => 53.1933, 'longitude' => -1.6511, 'description' => 'The reach below Haddon Hall.'],
            ],
            'dove' => [
                ['name' => 'Beresford Dale', 'latitude' => 53.1331, 'longitude' => -1.8134, 'description' => 'The upper dale below Hartington.'],
                ['name' => 'Milldale', 'latitude' => 53.0900, 'longitude' => -1.7929, 'description' => 'Viator\'s Bridge in the hamlet.'],
                ['name' => 'Dovedale Stepping Stones', 'latitude' => 53.0604, 'longitude' => -1.7783, 'description' => 'The stepping stones below Thorpe Cloud.'],
                ['name' => 'Hanging Bridge', 'latitude' => 53.0098, 'longitude' => -1.7655, 'description' => 'Below the bridge at Mayfield, near Ashbourne.'],
                ['name' => 'Rocester', 'latitude' => 52.9523, 'longitude' => -1.8323, 'description' => 'Below the village, where the Churnet joins.'],
                ['name' => 'Tutbury', 'latitude' => 52.8616, 'longitude' => -1.6799, 'description' => 'The riverside below Tutbury castle.'],
            ],
            'manifold' => [
                ['name' => 'Wetton Mill', 'latitude' => 53.1202, 'longitude' => -1.8577, 'description' => 'The tearoom reach, where the river often runs underground in summer.'],
                ['name' => 'Ilam', 'latitude' => 53.0525, 'longitude' => -1.8024, 'description' => 'Ilam Park, just above where the Manifold joins the Dove.'],
            ],
            'trent' => [
                ['name' => 'Great Haywood', 'latitude' => 52.7999, 'longitude' => -2.0075, 'description' => 'Essex Bridge at Shugborough, where the Sow joins.'],
                ['name' => 'Alrewas', 'latitude' => 52.7364, 'longitude' => -1.7497, 'description' => 'The village reach by the canal.'],
                ['name' => 'Burton upon Trent', 'latitude' => 52.8074, 'longitude' => -1.6238, 'description' => 'The Washlands between the town bridges.'],
                ['name' => 'Swarkestone', 'latitude' => 52.8529, 'longitude' => -1.4527, 'description' => 'Swarkestone Bridge and its causeway.'],
                ['name' => 'Shardlow', 'latitude' => 52.8656, 'longitude' => -1.3464, 'description' => 'The village reach, where the Derwent joins.'],
                ['name' => 'Attenborough', 'latitude' => 52.8953, 'longitude' => -1.2298, 'description' => 'The nature reserve lakes and riverside.'],
                ['name' => 'Trent Bridge, Nottingham', 'latitude' => 52.9361, 'longitude' => -1.1379, 'description' => 'Victoria Embankment by Trent Bridge.'],
                ['name' => 'Holme Pierrepont', 'latitude' => 52.9479, 'longitude' => -1.0880, 'description' => 'Beside the National Water Sports Centre.'],
                ['name' => 'Gunthorpe', 'latitude' => 52.9863, 'longitude' => -0.9872, 'description' => 'Gunthorpe lock and bridge.'],
                ['name' => 'Newark', 'latitude' => 53.0785, 'longitude' => -0.8126, 'description' => 'The castle reach and town lock.'],
                ['name' => 'Gainsborough', 'latitude' => 53.4004, 'longitude' => -0.7822, 'description' => 'The riverside walk, on the tidal Trent.'],
            ],
            'soar' => [
                ['name' => 'Leicester', 'latitude' => 52.6320, 'longitude' => -1.1422, 'description' => 'Castle Gardens on the Soar.'],
                ['name' => 'Barrow upon Soar', 'latitude' => 52.7517, 'longitude' => -1.1529, 'description' => 'The village reach and lock.'],
                ['name' => 'Zouch', 'latitude' => 52.8062, 'longitude' => -1.2456, 'description' => 'Zouch lock and the cut.'],
            ],
            'severn' => [
                ['name' => 'Shrewsbury', 'latitude' => 52.7099, 'longitude' => -2.7592, 'description' => 'The Quarry park reach, below the town walls.'],
                ['name' => 'Atcham', 'latitude' => 52.6786, 'longitude' => -2.6813, 'description' => 'The old bridge by Attingham Park.'],
                ['name' => 'Cressage', 'latitude' => 52.6403, 'longitude' => -2.5994, 'description' => 'Cressage bridge and the riverside.'],
                ['name' => 'Ironbridge', 'latitude' => 52.6273, 'longitude' => -2.4858, 'description' => 'Below the Iron Bridge in the gorge.'],
                ['name' => 'Bridgnorth', 'latitude' => 52.5340, 'longitude' => -2.4158, 'description' => 'The riverside below the high town.'],
                ['name' => 'Hampton Loade', 'latitude' => 52.4678, 'longitude' => -2.3767, 'description' => 'The ferry crossing and Severn Valley Railway halt.'],
                ['name' => 'Bewdley', 'latitude' => 52.3766, 'longitude' => -2.3140, 'description' => 'The riverside below Telford\'s bridge.'],
                ['name' => 'Stourport-on-Severn', 'latitude' => 52.3365, 'longitude' => -2.2812, 'description' => 'The riverside by the canal basins.'],
                ['name' => 'Worcester', 'latitude' => 52.1911, 'longitude' => -2.2259, 'description' => 'The cathedral reach and Diglis.'],
                ['name' => 'Upton upon Severn', 'latitude' => 52.0641, 'longitude' => -2.2136, 'description' => 'The riverside by the town bridge.'],
            ],
            'teme' => [
                ['name' => 'Leintwardine', 'latitude' => 52.3592, 'longitude' => -2.8761, 'description' => 'The village bridge, where the Clun joins.'],
                ['name' => 'Ludlow', 'latitude' => 52.3662, 'longitude' => -2.7256, 'description' => 'Dinham Bridge below the castle, a designated bathing water.'],
                ['name' => 'Tenbury Wells', 'latitude' => 52.3124, 'longitude' => -2.5968, 'description' => 'The town bridge and riverside.'],
                ['name' => 'Knightwick', 'latitude' => 52.2013, 'longitude' => -2.3919, 'description' => 'Below the bridge at Knightwick.'],
            ],
            'avon' => [
                ['name' => 'Warwick', 'latitude' => 52.2793, 'longitude' => -1.5831, 'description' => 'St Nicholas Park, below the castle.'],
                ['name' => 'Stratford-upon-Avon', 'latitude' => 52.1899, 'longitude' => -1.7038, 'description' => 'The riverside gardens by the theatre.'],
                ['name' => 'Bidford-on-Avon', 'latitude' => 52.1637, 'longitude' => -1.8583, 'description' => 'The medieval bridge and riverside.'],
                ['name' => 'Evesham', 'latitude' => 52.0913, 'longitude' => -1.9433, 'description' => 'Abbey Park and the riverside.'],
                ['name' => 'Pershore', 'latitude' => 52.1079, 'longitude' => -2.0706, 'description' => 'The old bridge and riverside meadows.'],
                ['name' => 'Tewkesbury', 'latitude' => 51.9983, 'longitude' => -2.1554, 'description' => 'Where the Avon meets the Severn, by the abbey.'],
            ],
        ];
    }

    /**
     * Spots on the Northumbrian Water rivers. Coordinates are snapped onto the OS centreline.
     *
     * @return array<string, array<int, array{name: string, latitude: float, longitude: float, description: string}>>
     */
    private static function northEast(): array
    {
        return [
            'south-tyne' => [
                ['name' => 'Alston', 'latitude' => 54.8100, 'longitude' => -2.4431, 'description' => 'Below the town bridge, the highest market town in England.'],
                ['name' => 'Lambley Viaduct', 'latitude' => 54.9280, 'longitude' => -2.5072, 'description' => 'Pools below the railway viaduct, on the South Tyne Trail.'],
                ['name' => 'Haltwhistle', 'latitude' => 54.9664, 'longitude' => -2.4557, 'description' => 'Riverside below the town, near the Haltwhistle Burn.'],
                ['name' => 'Bardon Mill', 'latitude' => 54.9738, 'longitude' => -2.3449, 'description' => 'Village footbridge and shingle banks.'],
                ['name' => 'Haydon Bridge', 'latitude' => 54.9745, 'longitude' => -2.2449, 'description' => 'The old bridge and riverside green.'],
            ],
            'north-tyne' => [
                ['name' => 'Bellingham', 'latitude' => 55.1420, 'longitude' => -2.2570, 'description' => 'Below the bridge, near where the Rede joins.'],
                ['name' => 'Wark', 'latitude' => 55.0861, 'longitude' => -2.2160, 'description' => 'Riverside village below Wark Common.'],
                ['name' => 'Chollerford', 'latitude' => 55.0305, 'longitude' => -2.1237, 'description' => 'Chollerford bridge, beside Chesters Roman fort.'],
            ],
            'tyne' => [
                ['name' => 'Hexham', 'latitude' => 54.9774, 'longitude' => -2.0965, 'description' => 'Tyne Green and the bridge, below the North and South Tyne meeting.'],
                ['name' => 'Corbridge', 'latitude' => 54.9707, 'longitude' => -2.0150, 'description' => 'The old bridge and riverside path.'],
                ['name' => 'Riding Mill', 'latitude' => 54.9508, 'longitude' => -1.9714, 'description' => 'Riverside meadows below the village.'],
                ['name' => 'Bywell', 'latitude' => 54.9500, 'longitude' => -1.9237, 'description' => 'Bywell bridge and the shingle reach by the castle.'],
                ['name' => 'Ovingham', 'latitude' => 54.9661, 'longitude' => -1.8680, 'description' => 'Footbridge and riverside, opposite Prudhoe.'],
                ['name' => 'Wylam', 'latitude' => 54.9738, 'longitude' => -1.8204, 'description' => 'Wylam bridge and the riverside park, near the tidal limit.'],
                ['name' => 'Newburn', 'latitude' => 54.9818, 'longitude' => -1.7472, 'description' => 'Newburn Riverside, where the Tyne turns tidal.'],
            ],
            'wear' => [
                ['name' => 'Stanhope', 'latitude' => 54.7460, 'longitude' => -2.0079, 'description' => 'Stepping stones and the riverside by Durham Street.'],
                ['name' => 'Frosterley', 'latitude' => 54.7258, 'longitude' => -1.9620, 'description' => 'Riverside below the village.'],
                ['name' => 'Wolsingham', 'latitude' => 54.7287, 'longitude' => -1.8817, 'description' => 'Riverside path and bridge below the town.'],
                ['name' => 'Witton-le-Wear', 'latitude' => 54.6714, 'longitude' => -1.7722, 'description' => 'Riverside by the village and the castle.'],
                ['name' => 'Bishop Auckland', 'latitude' => 54.6679, 'longitude' => -1.6799, 'description' => 'Newton Cap bridge, below Auckland Castle.'],
                ['name' => 'Durham', 'latitude' => 54.7734, 'longitude' => -1.5789, 'description' => 'Prebends Bridge and the wooded banks below the cathedral.'],
                ['name' => 'Finchale Priory', 'latitude' => 54.8174, 'longitude' => -1.5528, 'description' => 'Riverside meadows by the priory ruins.'],
                ['name' => 'Chester-le-Street', 'latitude' => 54.8581, 'longitude' => -1.5657, 'description' => 'Riverside Park, below the cricket ground.'],
            ],
            'tees' => [
                ['name' => 'High Force', 'latitude' => 54.6506, 'longitude' => -2.1866, 'description' => 'Below the waterfall, in Upper Teesdale.'],
                ['name' => 'Low Force', 'latitude' => 54.6458, 'longitude' => -2.1543, 'description' => 'Falls and Wynch Bridge near Bowlees.'],
                ['name' => 'Middleton-in-Teesdale', 'latitude' => 54.6223, 'longitude' => -2.0841, 'description' => 'Riverside path by the town bridge.'],
                ['name' => 'Eggleston', 'latitude' => 54.6046, 'longitude' => -2.0079, 'description' => 'Eggleston Bridge, below the village.'],
                ['name' => 'Cotherstone', 'latitude' => 54.5757, 'longitude' => -1.9795, 'description' => 'Where the Balder joins, below the village.'],
                ['name' => 'Barnard Castle', 'latitude' => 54.5428, 'longitude' => -1.9273, 'description' => 'County Bridge and the riverside below the castle.'],
                ['name' => 'Whorlton', 'latitude' => 54.5282, 'longitude' => -1.8460, 'description' => 'Whorlton suspension bridge and the riverside lido area.'],
                ['name' => 'Gainford', 'latitude' => 54.5450, 'longitude' => -1.7342, 'description' => 'Riverside by the village green.'],
                ['name' => 'Piercebridge', 'latitude' => 54.5347, 'longitude' => -1.6765, 'description' => 'The bridge and riverside by the Roman fort.'],
                ['name' => 'Croft-on-Tees', 'latitude' => 54.4843, 'longitude' => -1.5546, 'description' => 'Croft bridge, where the Tees meets the Yorkshire border.'],
                ['name' => 'Yarm', 'latitude' => 54.5093, 'longitude' => -1.3518, 'description' => 'Riverside below the town bridge and viaduct.'],
                ['name' => 'Preston Park', 'latitude' => 54.5339, 'longitude' => -1.3230, 'description' => 'Riverside path by the park, above the Tees Barrage.'],
            ],
            'coquet' => [
                ['name' => 'Alwinton', 'latitude' => 55.3440, 'longitude' => -2.1197, 'description' => 'Upper Coquetdale, where the Alwin joins.'],
                ['name' => 'Harbottle', 'latitude' => 55.3374, 'longitude' => -2.1034, 'description' => 'Riverside below the village and castle.'],
                ['name' => 'Rothbury', 'latitude' => 55.3080, 'longitude' => -1.9099, 'description' => 'The bridge and riverside below the town.'],
                ['name' => 'Felton', 'latitude' => 55.2966, 'longitude' => -1.7097, 'description' => 'The old bridge and riverside.'],
                ['name' => 'Warkworth', 'latitude' => 55.3459, 'longitude' => -1.6133, 'description' => 'Below the castle, above the tidal reach.'],
            ],
            'aln' => [
                ['name' => 'Alnwick', 'latitude' => 55.4181, 'longitude' => -1.7078, 'description' => 'Lion Bridge, below Alnwick Castle.'],
                ['name' => 'Lesbury', 'latitude' => 55.3975, 'longitude' => -1.6337, 'description' => 'Lesbury bridge, above the estuary at Alnmouth.'],
            ],
            'wansbeck' => [
                ['name' => 'Mitford', 'latitude' => 55.1641, 'longitude' => -1.7329, 'description' => 'Where the Font joins, below Mitford castle.'],
                ['name' => 'Morpeth', 'latitude' => 55.1672, 'longitude' => -1.6885, 'description' => 'Carlisle Park and the riverside through the town.'],
                ['name' => 'Bothal', 'latitude' => 55.1707, 'longitude' => -1.6254, 'description' => 'Wooded riverside below Bothal castle.'],
                ['name' => 'Sheepwash', 'latitude' => 55.1706, 'longitude' => -1.5898, 'description' => 'Riverside path near Sheepwash bridge.'],
            ],
            'blyth' => [
                ['name' => 'Plessey Woods', 'latitude' => 55.1087, 'longitude' => -1.6366, 'description' => 'Hartford Bridge and the country park.'],
                ['name' => 'Humford Mill', 'latitude' => 55.1151, 'longitude' => -1.6088, 'description' => 'The weir and riverside below Bedlington.'],
            ],
            'tweed' => [
                ['name' => 'Peebles', 'latitude' => 55.6500, 'longitude' => -3.1894, 'description' => 'The town reach by Tweed Bridge and Hay Lodge Park.'],
                ['name' => 'Innerleithen', 'latitude' => 55.6144, 'longitude' => -3.0565, 'description' => 'The riverside below the town, where the Leithen Water joins.'],
                ['name' => 'Walkerburn', 'latitude' => 55.6227, 'longitude' => -3.0103, 'description' => 'The village reach on the Tweed Valley path.'],
                ['name' => 'Melrose', 'latitude' => 55.6032, 'longitude' => -2.7211, 'description' => 'The chain bridge at Gattonside, below the abbey.'],
                ['name' => 'Dryburgh', 'latitude' => 55.5793, 'longitude' => -2.6514, 'description' => 'The suspension bridge by Dryburgh Abbey.'],
                ['name' => 'Kelso', 'latitude' => 55.5960, 'longitude' => -2.4347, 'description' => 'Below Rennie\'s bridge, where the Teviot joins.'],
                ['name' => 'Coldstream', 'latitude' => 55.6492, 'longitude' => -2.2454, 'description' => 'Below Coldstream Bridge, on the border.'],
                ['name' => 'Norham', 'latitude' => 55.7229, 'longitude' => -2.1623, 'description' => 'The reach below Norham castle.'],
                ['name' => 'Paxton', 'latitude' => 55.7535, 'longitude' => -2.1076, 'description' => 'Union Chain Bridge, the oldest suspension bridge still carrying traffic.'],
            ],
            'till' => [
                ['name' => 'Chatton', 'latitude' => 55.5451, 'longitude' => -1.9093, 'description' => 'Riverside below the village.'],
                ['name' => 'Ford', 'latitude' => 55.6304, 'longitude' => -2.0979, 'description' => 'Ford bridge and the riverside below the village.'],
                ['name' => 'Etal', 'latitude' => 55.6492, 'longitude' => -2.1206, 'description' => 'The ford and riverside by Etal castle.'],
                ['name' => 'Twizel Bridge', 'latitude' => 55.6808, 'longitude' => -2.2001, 'description' => 'The old bridge, above where the Till joins the Tweed.'],
            ],
        ];
    }

    /**
     * @return array<string, array<int, array{name: string, latitude: float, longitude: float, description: string}>>
     */
    private static function northWest(): array
    {
        return [
            'ribble' => [
                ['name' => 'Horton in Ribblesdale', 'latitude' => 54.1495, 'longitude' => -2.2964, 'description' => 'The upper Ribble by the village, below Pen-y-ghent.'],
                ['name' => 'Stainforth Force', 'latitude' => 54.0980, 'longitude' => -2.2804, 'description' => 'Waterfall and plunge pool below the packhorse bridge.'],
                ['name' => 'Settle', 'latitude' => 54.0688, 'longitude' => -2.2857, 'description' => 'Settle Bridge and the riverside path to Langcliffe.'],
                ['name' => 'Sawley', 'latitude' => 53.9149, 'longitude' => -2.3449, 'description' => 'Below the abbey ruins.'],
                ['name' => 'Edisford Bridge', 'latitude' => 53.8697, 'longitude' => -2.4159, 'description' => 'Riverside picnic area west of Clitheroe.'],
                ['name' => 'Ribchester', 'latitude' => 53.8108, 'longitude' => -2.5311, 'description' => 'Roman fort village and riverside meadows.'],
                ['name' => 'Avenham Park, Preston', 'latitude' => 53.7520, 'longitude' => -2.6965, 'description' => 'Park reach by the Old Tram Bridge, near the tidal limit.'],
            ],
            'lune' => [
                ['name' => 'Tebay', 'latitude' => 54.4293, 'longitude' => -2.5976, 'description' => 'Where the Lune turns south into the Lune Gorge.'],
                ['name' => 'Lincoln\'s Inn Bridge', 'latitude' => 54.3259, 'longitude' => -2.5677, 'description' => 'Rocky pools west of Sedbergh.'],
                ['name' => 'Kirkby Lonsdale', 'latitude' => 54.2003, 'longitude' => -2.5892, 'description' => 'Devil\'s Bridge and its deep pools.'],
                ['name' => 'Loyn Bridge', 'latitude' => 54.1185, 'longitude' => -2.6448, 'description' => 'Below Hornby, near where the Wenning joins.'],
                ['name' => 'Crook o\' Lune', 'latitude' => 54.0843, 'longitude' => -2.7157, 'description' => 'Wooded loop and picnic area east of Lancaster.'],
                ['name' => 'Halton', 'latitude' => 54.0750, 'longitude' => -2.7644, 'description' => 'Weir and riverside path at Halton.'],
                ['name' => 'Lancaster', 'latitude' => 54.0547, 'longitude' => -2.8067, 'description' => 'Skerton weir, the tidal limit.'],
            ],
            'kent' => [
                ['name' => 'Kentmere', 'latitude' => 54.4281, 'longitude' => -2.8394, 'description' => 'Upper valley below the reservoir.'],
                ['name' => 'Staveley', 'latitude' => 54.3753, 'longitude' => -2.8137, 'description' => 'Village weir and riverside path.'],
                ['name' => 'Burneside', 'latitude' => 54.3553, 'longitude' => -2.7651, 'description' => 'Below the paper mill village.'],
                ['name' => 'Kendal', 'latitude' => 54.3274, 'longitude' => -2.7434, 'description' => 'Gooseholme and the town riverside.'],
                ['name' => 'Sedgwick', 'latitude' => 54.2810, 'longitude' => -2.7559, 'description' => 'Force Falls and the old gunpowder works.'],
                ['name' => 'Levens Bridge', 'latitude' => 54.2680, 'longitude' => -2.7646, 'description' => 'Below Levens Hall, near the head of the estuary.'],
            ],
            'eden' => [
                ['name' => 'Stenkrith Park', 'latitude' => 54.4622, 'longitude' => -2.3483, 'description' => 'Rock gorge and pools south of Kirkby Stephen.'],
                ['name' => 'Appleby', 'latitude' => 54.5788, 'longitude' => -2.4902, 'description' => 'Town riverside and the horse fair ford.'],
                ['name' => 'Langwathby', 'latitude' => 54.6936, 'longitude' => -2.6682, 'description' => 'Village bridge and shingle banks.'],
                ['name' => 'Lazonby', 'latitude' => 54.7527, 'longitude' => -2.6970, 'description' => 'Pools below the bridge by the riverside campsite.'],
                ['name' => 'Armathwaite', 'latitude' => 54.8071, 'longitude' => -2.7675, 'description' => 'Weir and sandstone gorge below the castle.'],
                ['name' => 'Wetheral', 'latitude' => 54.8837, 'longitude' => -2.8294, 'description' => 'Below the viaduct and Corby Castle.'],
                ['name' => 'Rickerby Park, Carlisle', 'latitude' => 54.8994, 'longitude' => -2.9205, 'description' => 'City park on the Eden.'],
            ],
            'derwent' => [
                ['name' => 'Grange in Borrowdale', 'latitude' => 54.5530, 'longitude' => -3.1490, 'description' => 'Clear pools by the double-arched bridge.'],
                ['name' => 'Portinscale', 'latitude' => 54.6016, 'longitude' => -3.1529, 'description' => 'Where the Derwent leaves Derwentwater.'],
                ['name' => 'Isel Bridge', 'latitude' => 54.6907, 'longitude' => -3.3099, 'description' => 'Quiet reach by Isel church.'],
                ['name' => 'Cockermouth', 'latitude' => 54.6651, 'longitude' => -3.3650, 'description' => 'Where the Cocker joins, below the castle.'],
                ['name' => 'Workington Bridge', 'latitude' => 54.6490, 'longitude' => -3.5327, 'description' => 'The last reach above the harbour.'],
            ],
        ];
    }

    /**
     * @return array<string, array<int, array{name: string, latitude: float, longitude: float, description: string}>>
     */
    private static function thamesValley(): array
    {
        return [
            'thames' => [
                ['name' => 'Ashton Keynes', 'latitude' => 51.6437, 'longitude' => -1.9339, 'description' => 'The infant Thames running through the village.'],
                ['name' => 'Cricklade', 'latitude' => 51.6444, 'longitude' => -1.8554, 'description' => 'North Meadow and the first town on the Thames.'],
                ['name' => 'Lechlade', 'latitude' => 51.6921, 'longitude' => -1.6891, 'description' => "Ha'penny Bridge and the head of navigation."],
                ['name' => 'Kelmscott', 'latitude' => 51.6868, 'longitude' => -1.6392, 'description' => 'Meadows below Kelmscott Manor.'],
                ['name' => 'Radcot Bridge', 'latitude' => 51.6938, 'longitude' => -1.5864, 'description' => 'The oldest bridge on the Thames.'],
                ['name' => 'Tadpole Bridge', 'latitude' => 51.7112, 'longitude' => -1.4631, 'description' => 'Quiet upper Thames reach by the Trout inn.'],
                ['name' => 'Newbridge', 'latitude' => 51.7099, 'longitude' => -1.4180, 'description' => 'Where the Windrush meets the Thames.'],
                ['name' => 'Bablock Hythe', 'latitude' => 51.7362, 'longitude' => -1.3736, 'description' => 'Old ferry crossing west of Oxford.'],
                ['name' => 'Port Meadow, Oxford', 'latitude' => 51.7663, 'longitude' => -1.2792, 'description' => 'Common land and a long-standing swimming spot.'],
                ['name' => 'Sandford Lock', 'latitude' => 51.7113, 'longitude' => -1.2329, 'description' => 'Below Oxford, by the lasher.'],
                ['name' => 'Abingdon', 'latitude' => 51.6685, 'longitude' => -1.2805, 'description' => 'Abbey Meadows and the town bridge.'],
                ['name' => 'Clifton Hampden', 'latitude' => 51.6556, 'longitude' => -1.2094, 'description' => 'Brick bridge and riverside meadows.'],
                ['name' => 'Wallingford', 'latitude' => 51.5984, 'longitude' => -1.1212, 'description' => 'Riverside meadows by the medieval bridge.'],
                ['name' => 'Goring', 'latitude' => 51.5222, 'longitude' => -1.1418, 'description' => 'Goring Gap, between Goring and Streatley.'],
                ['name' => 'Pangbourne', 'latitude' => 51.4871, 'longitude' => -1.0835, 'description' => 'Pangbourne Meadow below Whitchurch Bridge.'],
                ['name' => 'Sonning', 'latitude' => 51.4761, 'longitude' => -0.9136, 'description' => 'Sonning Bridge and the lock.'],
                ['name' => 'Henley-on-Thames', 'latitude' => 51.5372, 'longitude' => -0.9003, 'description' => 'The regatta reach and Mill Meadows.'],
                ['name' => 'Marlow', 'latitude' => 51.5670, 'longitude' => -0.7738, 'description' => 'Higginson Park by the suspension bridge.'],
                ['name' => 'Cookham', 'latitude' => 51.5627, 'longitude' => -0.7075, 'description' => 'Cookham Moor and the bridge.'],
                ['name' => 'Maidenhead', 'latitude' => 51.5299, 'longitude' => -0.7004, 'description' => "Boulter's Lock and Ray Mill Island."],
                ['name' => 'Windsor', 'latitude' => 51.4861, 'longitude' => -0.6081, 'description' => 'Alexandra Gardens and the Brocas meadow.'],
                ['name' => 'Runnymede', 'latitude' => 51.4419, 'longitude' => -0.5566, 'description' => 'National Trust meadows below Old Windsor.'],
                ['name' => 'Staines', 'latitude' => 51.4330, 'longitude' => -0.5161, 'description' => 'Below the Colne confluence.'],
                ['name' => 'Shepperton', 'latitude' => 51.3867, 'longitude' => -0.4401, 'description' => 'Shepperton Lock and the Wey confluence.'],
                ['name' => 'Hampton Court', 'latitude' => 51.4026, 'longitude' => -0.3400, 'description' => 'By the palace, below the Mole.'],
                ['name' => 'Kingston upon Thames', 'latitude' => 51.4110, 'longitude' => -0.3089, 'description' => 'Canbury Gardens and the town bridge.'],
                ['name' => 'Teddington Lock', 'latitude' => 51.4303, 'longitude' => -0.3226, 'description' => 'The tidal limit of the Thames.'],
            ],
        ];
    }

    /**
     * @return array<string, array<int, array{name: string, latitude: float, longitude: float, description: string}>>
     */
    private static function yorkshire(): array
    {
        return [
            'wharfe' => [
                ['name' => 'Kettlewell', 'latitude' => 54.1442, 'longitude' => -2.0496, 'description' => 'Upper Wharfedale village with riverside access by the bridge.'],
                ['name' => 'Linton Falls, Grassington', 'latitude' => 54.0675, 'longitude' => -2.0034, 'description' => 'Popular falls and footbridge below Grassington.'],
                ['name' => 'Burnsall', 'latitude' => 54.0459, 'longitude' => -1.9521, 'description' => 'Riverside green and bridge, busy on summer weekends.'],
                ['name' => 'Bolton Abbey', 'latitude' => 53.9902, 'longitude' => -1.8804, 'description' => 'Cavendish Pavilion and the stepping stones.'],
                ['name' => 'Addingham', 'latitude' => 53.9459, 'longitude' => -1.8748, 'description' => 'Riverside path and suspension bridge.'],
                ['name' => 'Ilkley', 'latitude' => 53.9291, 'longitude' => -1.8192, 'description' => 'Designated bathing water at Cromwheel, by the Old Bridge.'],
                ['name' => 'Burley-in-Wharfedale', 'latitude' => 53.9165, 'longitude' => -1.7442, 'description' => 'Riverside walk below the village.'],
                ['name' => 'Otley', 'latitude' => 53.9079, 'longitude' => -1.6959, 'description' => 'Wharfemeadows Park and the Otley gauge.'],
                ['name' => 'Pool-in-Wharfedale', 'latitude' => 53.9052, 'longitude' => -1.6301, 'description' => 'Pool Bridge and the riverside fields.'],
                ['name' => 'Harewood Bridge', 'latitude' => 53.9098, 'longitude' => -1.5262, 'description' => 'Below the Harewood estate on the A61.'],
                ['name' => 'Wetherby', 'latitude' => 53.9282, 'longitude' => -1.3895, 'description' => 'Wetherby weir and the riverside.'],
                ['name' => 'Boston Spa', 'latitude' => 53.9069, 'longitude' => -1.3453, 'description' => 'Riverside walk and weir.'],
                ['name' => 'Tadcaster', 'latitude' => 53.8852, 'longitude' => -1.2601, 'description' => 'Lower Wharfe, tidal below the weir.'],
            ],
            'aire' => [
                ['name' => 'Malham', 'latitude' => 54.0544, 'longitude' => -2.1510, 'description' => 'Aire Head springs just below the village.'],
                ['name' => 'Gargrave', 'latitude' => 53.9826, 'longitude' => -2.1041, 'description' => 'Village green and riverside beside the canal.'],
                ['name' => 'Skipton', 'latitude' => 53.9467, 'longitude' => -2.0246, 'description' => 'Aire south of the town centre.'],
                ['name' => 'Keighley', 'latitude' => 53.8762, 'longitude' => -1.8906, 'description' => 'Stockbridge and the riverside path.'],
                ['name' => 'Bingley', 'latitude' => 53.8492, 'longitude' => -1.8405, 'description' => 'Myrtle Park and the river below Five Rise Locks.'],
                ['name' => 'Saltaire', 'latitude' => 53.8400, 'longitude' => -1.7900, 'description' => 'Roberts Park and the boathouse.'],
                ['name' => 'Apperley Bridge', 'latitude' => 53.8387, 'longitude' => -1.7070, 'description' => 'Riverside below the marina.'],
                ['name' => 'Rodley', 'latitude' => 53.8194, 'longitude' => -1.6436, 'description' => 'Rodley Nature Reserve and the riverside path.'],
                ['name' => 'Kirkstall', 'latitude' => 53.8195, 'longitude' => -1.6061, 'description' => 'Kirkstall Abbey riverside meadows.'],
                ['name' => 'Leeds city centre', 'latitude' => 53.7936, 'longitude' => -1.5346, 'description' => 'Leeds Dock and Brewery Wharf.'],
                ['name' => 'Woodlesford', 'latitude' => 53.7612, 'longitude' => -1.4463, 'description' => 'Fleet Weir and the Aire and Calder Navigation.'],
                ['name' => 'Castleford', 'latitude' => 53.7281, 'longitude' => -1.3558, 'description' => 'Millennium Bridge below the Calder confluence.'],
            ],
            'calder' => [
                ['name' => 'Todmorden', 'latitude' => 53.7147, 'longitude' => -2.0956, 'description' => 'Upper Calder through the town, beside the Rochdale Canal.'],
                ['name' => 'Hebden Bridge', 'latitude' => 53.7398, 'longitude' => -2.0144, 'description' => 'Calder Holmes Park and the packhorse bridge.'],
                ['name' => 'Mytholmroyd', 'latitude' => 53.7314, 'longitude' => -1.9848, 'description' => 'Riverside below the village bridge.'],
                ['name' => 'Sowerby Bridge', 'latitude' => 53.7089, 'longitude' => -1.9069, 'description' => 'Below the Ryburn confluence by the canal basin.'],
                ['name' => 'Copley', 'latitude' => 53.7010, 'longitude' => -1.8777, 'description' => 'Copley village and the riverside path to Salterhebble.'],
                ['name' => 'Brighouse', 'latitude' => 53.6993, 'longitude' => -1.7820, 'description' => 'Wellholme Park and the river through the town.'],
                ['name' => 'Mirfield', 'latitude' => 53.6743, 'longitude' => -1.6987, 'description' => 'Riverside below Mirfield and the canal towpath.'],
                ['name' => 'Dewsbury', 'latitude' => 53.6881, 'longitude' => -1.6308, 'description' => 'Savile Town and the river below the town centre.'],
                ['name' => 'Horbury Bridge', 'latitude' => 53.6566, 'longitude' => -1.5666, 'description' => 'Horbury Bridge and the riverside path.'],
                ['name' => 'Wakefield', 'latitude' => 53.6765, 'longitude' => -1.4919, 'description' => 'Chantry Bridge and the Hepworth riverside.'],
                ['name' => 'Stanley Ferry', 'latitude' => 53.7025, 'longitude' => -1.4627, 'description' => 'Lower Calder by the aqueduct and marina.'],
            ],
            'don' => [
                ['name' => 'Penistone', 'latitude' => 53.5264, 'longitude' => -1.6182, 'description' => 'Upper Don below the town.'],
                ['name' => 'Deepcar', 'latitude' => 53.4765, 'longitude' => -1.5640, 'description' => 'Below the Little Don confluence, by the Trans Pennine Trail.'],
                ['name' => 'Oughtibridge', 'latitude' => 53.4378, 'longitude' => -1.5342, 'description' => 'Village bridge and riverside path.'],
                ['name' => 'Hillsborough', 'latitude' => 53.4104, 'longitude' => -1.4997, 'description' => 'Riverside walk by Hillsborough Park.'],
                ['name' => 'Kelham Island', 'latitude' => 53.3897, 'longitude' => -1.4713, 'description' => 'Sheffield city centre, by the museum weir.'],
                ['name' => 'Meadowhall', 'latitude' => 53.4159, 'longitude' => -1.4126, 'description' => 'Five Weirs Walk below the shopping centre.'],
                ['name' => 'Rotherham', 'latitude' => 53.4321, 'longitude' => -1.3588, 'description' => 'Town centre riverside below the Rother confluence.'],
                ['name' => 'Sprotbrough', 'latitude' => 53.5066, 'longitude' => -1.1910, 'description' => 'Sprotbrough Falls and the Don Gorge.'],
                ['name' => 'Doncaster', 'latitude' => 53.5310, 'longitude' => -1.1372, 'description' => 'North Bridge and the riverside path.'],
                ['name' => 'Stainforth', 'latitude' => 53.6043, 'longitude' => -1.0285, 'description' => 'Lower Don beside the Stainforth and Keadby Canal.'],
            ],
            'nidd' => [
                ['name' => 'Pateley Bridge', 'latitude' => 54.0870, 'longitude' => -1.7645, 'description' => 'Riverside park and the town bridge.'],
                ['name' => 'Summerbridge', 'latitude' => 54.0585, 'longitude' => -1.6979, 'description' => 'Bridge over the Nidd below Dacre Banks.'],
                ['name' => 'Darley', 'latitude' => 54.0374, 'longitude' => -1.6639, 'description' => 'Darley Mill and the riverside.'],
                ['name' => 'Birstwith', 'latitude' => 54.0327, 'longitude' => -1.6279, 'description' => 'Packhorse bridge and the river below the village.'],
                ['name' => 'Hampsthwaite', 'latitude' => 54.0277, 'longitude' => -1.6035, 'description' => 'Village bridge and riverside green.'],
                ['name' => 'Nidd Gorge', 'latitude' => 54.0208, 'longitude' => -1.5237, 'description' => 'Wooded gorge between Bilton and Knaresborough.'],
                ['name' => 'Knaresborough', 'latitude' => 54.0075, 'longitude' => -1.4706, 'description' => 'Waterside below the castle and viaduct.'],
                ['name' => 'Cattal', 'latitude' => 53.9812, 'longitude' => -1.3169, 'description' => 'Cattal bridge and the lower Nidd.'],
                ['name' => 'Nun Monkton', 'latitude' => 54.0144, 'longitude' => -1.2183, 'description' => 'Where the Nidd meets the Ouse.'],
            ],
            'ure' => [
                ['name' => 'Hawes', 'latitude' => 54.3079, 'longitude' => -2.1975, 'description' => 'Haylands Bridge north of the town.'],
                ['name' => 'Aysgarth Falls', 'latitude' => 54.2955, 'longitude' => -1.9949, 'description' => 'The upper, middle and lower falls.'],
                ['name' => 'Wensley', 'latitude' => 54.3002, 'longitude' => -1.8593, 'description' => 'Wensley Bridge and the riverside.'],
                ['name' => 'Middleham', 'latitude' => 54.2918, 'longitude' => -1.8121, 'description' => 'Middleham Bridge below the castle.'],
                ['name' => 'Masham', 'latitude' => 54.2250, 'longitude' => -1.6542, 'description' => 'Town bridge and the riverside walk.'],
                ['name' => 'West Tanfield', 'latitude' => 54.2041, 'longitude' => -1.5860, 'description' => 'Village bridge and the Marmion Tower reach.'],
                ['name' => 'Ripon', 'latitude' => 54.1436, 'longitude' => -1.5153, 'description' => 'North Bridge and the riverside below the racecourse.'],
                ['name' => 'Boroughbridge', 'latitude' => 54.0975, 'longitude' => -1.3941, 'description' => 'Town bridge and the Ure below the weir.'],
                ['name' => 'Aldwark Bridge', 'latitude' => 54.0542, 'longitude' => -1.2880, 'description' => 'Toll bridge on the lower Ure.'],
            ],
            'swale' => [
                ['name' => 'Keld', 'latitude' => 54.4053, 'longitude' => -2.1640, 'description' => 'Kisdon Force and the upper Swale.'],
                ['name' => 'Gunnerside', 'latitude' => 54.3778, 'longitude' => -2.0894, 'description' => 'Village bridge and the riverside meadows.'],
                ['name' => 'Grinton', 'latitude' => 54.3823, 'longitude' => -1.9356, 'description' => 'Grinton Bridge below Reeth.'],
                ['name' => 'Richmond', 'latitude' => 54.4008, 'longitude' => -1.7382, 'description' => 'Richmond Falls below the castle.'],
                ['name' => 'Catterick Bridge', 'latitude' => 54.3875, 'longitude' => -1.6604, 'description' => 'Old bridge by the racecourse.'],
                ['name' => 'Morton-on-Swale', 'latitude' => 54.3200, 'longitude' => -1.5118, 'description' => 'Village bridge and the riverside.'],
                ['name' => 'Topcliffe', 'latitude' => 54.1798, 'longitude' => -1.3898, 'description' => 'Topcliffe bridge and the weir.'],
                ['name' => 'Myton-on-Swale', 'latitude' => 54.0946, 'longitude' => -1.3355, 'description' => 'Lower Swale just above the Ure confluence.'],
            ],
            'derwent' => [
                ['name' => 'Forge Valley', 'latitude' => 54.2586, 'longitude' => -0.4850, 'description' => 'Boardwalk through the wooded valley above West Ayton.'],
                ['name' => 'Yedingham', 'latitude' => 54.2031, 'longitude' => -0.6272, 'description' => 'Village bridge on the Vale of Pickering reach.'],
                ['name' => 'Malton', 'latitude' => 54.1334, 'longitude' => -0.7980, 'description' => 'County bridge between Malton and Norton.'],
                ['name' => 'Kirkham Priory', 'latitude' => 54.0851, 'longitude' => -0.8824, 'description' => 'Riverside below the priory ruins.'],
                ['name' => 'Howsham', 'latitude' => 54.0578, 'longitude' => -0.8855, 'description' => 'Howsham weir and mill.'],
                ['name' => 'Stamford Bridge', 'latitude' => 53.9920, 'longitude' => -0.9132, 'description' => 'Village bridge and the riverside.'],
                ['name' => 'Sutton upon Derwent', 'latitude' => 53.9172, 'longitude' => -0.9317, 'description' => 'Village bridge and weir.'],
                ['name' => 'Bubwith', 'latitude' => 53.8193, 'longitude' => -0.9271, 'description' => 'Bubwith bridge on the lower Derwent.'],
                ['name' => 'Barmby on the Marsh', 'latitude' => 53.7499, 'longitude' => -0.9691, 'description' => 'Above the tidal barrage at the Ouse confluence.'],
            ],
            'ouse' => [
                ['name' => 'Newton-on-Ouse', 'latitude' => 54.0216, 'longitude' => -1.2187, 'description' => 'Riverside below the village, near Beningbrough.'],
                ['name' => 'Nether Poppleton', 'latitude' => 53.9890, 'longitude' => -1.1473, 'description' => 'Riverside green and the ferry reach.'],
                ['name' => 'York', 'latitude' => 53.9601, 'longitude' => -1.0876, 'description' => 'Lendal Bridge and the city centre riverside.'],
                ['name' => 'Bishopthorpe', 'latitude' => 53.9243, 'longitude' => -1.0894, 'description' => 'Riverside below the palace.'],
                ['name' => 'Naburn', 'latitude' => 53.9008, 'longitude' => -1.1035, 'description' => 'Naburn Locks, the tidal limit.'],
                ['name' => 'Cawood', 'latitude' => 53.8333, 'longitude' => -1.1276, 'description' => 'Swing bridge and the riverside.'],
                ['name' => 'Selby', 'latitude' => 53.7852, 'longitude' => -1.0646, 'description' => 'Toll bridge and the town riverside.'],
                ['name' => 'Goole', 'latitude' => 53.6990, 'longitude' => -0.8582, 'description' => 'Tidal Ouse by the docks.'],
            ],
            'esk' => [
                ['name' => 'Castleton', 'latitude' => 54.4662, 'longitude' => -0.9420, 'description' => 'Upper Esk below the village.'],
                ['name' => 'Danby', 'latitude' => 54.4600, 'longitude' => -0.8914, 'description' => 'Duck Bridge and the riverside.'],
                ['name' => 'Lealholm', 'latitude' => 54.4583, 'longitude' => -0.8253, 'description' => 'Village green, bridge and stepping stones.'],
                ['name' => 'Glaisdale', 'latitude' => 54.4416, 'longitude' => -0.7948, 'description' => 'Beggar\'s Bridge and the riverside path.'],
                ['name' => 'Egton Bridge', 'latitude' => 54.4358, 'longitude' => -0.7624, 'description' => 'Stepping stones and the village bridge.'],
                ['name' => 'Grosmont', 'latitude' => 54.4385, 'longitude' => -0.7262, 'description' => 'Riverside by the railway station.'],
                ['name' => 'Sleights', 'latitude' => 54.4614, 'longitude' => -0.6598, 'description' => 'Sleights bridge and the riverside.'],
                ['name' => 'Ruswarp', 'latitude' => 54.4690, 'longitude' => -0.6328, 'description' => 'Ruswarp weir, the tidal limit.'],
                ['name' => 'Whitby', 'latitude' => 54.4872, 'longitude' => -0.6132, 'description' => 'Upper harbour above the swing bridge.'],
            ],
            'hull' => [
                ['name' => 'Tickton', 'latitude' => 53.8593, 'longitude' => -0.3959, 'description' => 'Hull Bridge, east of Beverley.'],
                ['name' => 'Stoneferry', 'latitude' => 53.7653, 'longitude' => -0.3278, 'description' => 'Tidal River Hull north of the city centre.'],
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::spots() as $regionSlug => $rivers) {
            $region = Region::query()->where('slug', $regionSlug)->firstOrFail();

            foreach ($rivers as $riverSlug => $spots) {
                $river = $region->rivers()->where('slug', $riverSlug)->firstOrFail();

                foreach ($spots as $spot) {
                    $slug = Str::slug($spot['name']);

                    $river->spots()->updateOrCreate(['slug' => $slug], [...$spot, 'is_featured' => in_array($slug, self::FEATURED, true)]);
                }
            }
        }
    }
}
