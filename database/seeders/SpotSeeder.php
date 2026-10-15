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
            'north-west' => self::northWest(),
            'north-east' => self::northEast(),
            'midlands' => self::midlands(),
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
