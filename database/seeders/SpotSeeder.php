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
