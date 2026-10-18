<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\River;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RiverSeeder extends Seeder
{
    /**
     * Main rivers first, then tributaries (which reference their parent by
     * slug). Tributaries only contribute monitors; they get no pages. A main
     * river that joins another names it as flows_into, so its overflows
     * count upstream of that river's spots while it keeps its own pages. Names
     * must match OS Open Rivers; aliases are the receiving watercourse names
     * the overflow feed and the Environment Agency use. Main rivers carry an
     * approximate mouth so same-named rivers elsewhere in Britain are ignored.
     * Keyed by region slug; slugs only need to be unique within a region.
     *
     * @return array<string, array<int, array{name: string, os_name?: string|null, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null, also_runs_through?: array<string, array<int, string>>|null}>>
     */
    public static function rivers(): array
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
            'south-east' => self::southEast(),
            'scotland' => self::scotland(),
            'wales' => self::wales(),
        ];
    }

    /**
     * @return array<int, array{name: string, os_name?: string|null, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null, also_runs_through?: array<string, array<int, string>>|null}>
     */
    private static function yorkshire(): array
    {
        return [
            // Wharfe
            self::main('River Wharfe', 'Yorkshire Ouse', [53.8415, -1.1295], flowsInto: 'ouse'),
            self::tributary('River Washburn', 'wharfe'),
            self::tributary('Kex Beck', 'wharfe'),
            self::tributary('Weeton Beck', 'wharfe'),
            self::tributary('Keswick Beck', 'wharfe'),
            self::tributary('Bardsey Beck', 'keswick-beck'),
            self::tributary('Collingham Beck', 'wharfe'),
            self::tributary('Cock Beck', 'wharfe'),

            // Aire
            self::main('River Aire', 'Yorkshire Ouse', [53.7217, -0.8950], flowsInto: 'ouse'),
            self::tributary('Eller Beck', 'aire'),
            self::tributary('Silsden Beck', 'aire'),
            self::tributary('River Worth', 'aire'),
            self::tributary('Bridgehouse Beck', 'worth'),
            self::tributary('Loadpit Beck', 'aire'),
            self::tributary('Cottingley Beck', 'aire'),
            self::tributary('Bradford Beck', 'aire'),
            self::tributary('Farnley Wood Beck', 'aire'),
            self::tributary('Meanwood Beck', 'aire'),
            self::tributary('Adel Beck', 'meanwood-beck'),
            self::tributary('Wyke Beck', 'aire'),

            // Calder
            self::main('River Calder', 'Yorkshire Ouse', [53.7215, -1.3595], flowsInto: 'aire'),
            self::tributary('Walsden Water', 'calder'),
            self::tributary('Hebden Water', 'calder'),
            self::tributary('River Ryburn', 'calder'),
            self::tributary('Black Brook', 'calder'),
            self::tributary('Hebble Brook', 'calder'),
            self::tributary('Clifton Beck', 'calder'),
            self::tributary('River Colne', 'calder'),
            self::tributary('River Holme', 'colne'),
            self::tributary('Fenay Beck', 'colne'),

            // Don
            self::main('River Don', 'Yorkshire Ouse', [53.674, -0.980], flowsInto: 'ouse'),
            self::tributary('Ewden Beck', 'don'),
            self::tributary('River Loxley', 'don'),
            self::tributary('River Rivelin', 'loxley'),
            self::tributary('River Sheaf', 'don'),
            self::tributary('Porter Brook', 'sheaf'),
            self::tributary('Car Brook', 'don'),
            self::tributary('Blackburn Brook', 'don'),
            self::tributary('River Rother', 'don', ['River Rother and Tributaries']),
            self::tributary('River Hipper', 'rother'),
            self::tributary('River Drone', 'rother'),
            self::tributary('River Doe Lea', 'rother'),
            self::tributary('Shire Brook', 'rother'),
            self::tributary('River Dearne', 'don'),
            self::tributary('River Dove', 'dearne', slug: 'dove-dearne'),
            self::tributary('River Went', 'don'),

            // Nidd
            self::main('River Nidd', 'Yorkshire Ouse', [54.0205, -1.2185], flowsInto: 'ouse'),
            self::tributary('Oak Beck', 'nidd'),
            self::tributary('Crimple Beck', 'nidd', ['River Crimple', 'Crimple Beck Or River Crimple']),

            // Ure
            self::main('River Ure', 'Yorkshire Ouse', [54.039, -1.276], flowsInto: 'ouse'),
            self::tributary('River Bain', 'ure'),
            self::tributary('River Cover', 'ure'),
            self::tributary('River Burn', 'ure'),
            self::tributary('River Skell', 'ure'),
            self::tributary('River Laver', 'skell'),
            self::tributary('Bishop Monkton Beck', 'ure'),

            // Swale
            self::main('River Swale', 'Yorkshire Ouse', [54.089, -1.323], flowsInto: 'ure'),
            self::tributary('Skeeby Beck', 'swale'),
            self::tributary('Bedale Beck', 'swale'),
            self::tributary('River Wiske', 'swale'),
            self::tributary('Cod Beck', 'swale'),

            // Derwent
            self::main('River Derwent', 'Yorkshire Ouse', [53.749, -0.969], flowsInto: 'ouse'),
            self::tributary('River Hertford', 'derwent'),
            self::tributary('Thornton Beck', 'derwent'),
            self::tributary('River Rye', 'derwent'),
            self::tributary('River Seven', 'rye'),
            self::tributary('River Dove', 'rye'),
            self::tributary('Costa Beck', 'rye'),
            self::tributary('Pickering Beck', 'costa-beck'),

            // Ouse
            self::main('River Ouse', 'Yorkshire Ouse', [53.705, -0.712]),
            self::tributary('Ouse Gill Beck', 'ouse'),
            self::tributary('River Kyle', 'ouse'),
            self::tributary('Holgate Beck', 'ouse'),
            self::tributary('River Foss', 'ouse'),
            self::tributary('Tang Hall Beck', 'foss'),

            // Esk
            self::main('River Esk', 'Esk and Coast', [54.49, -0.611]),
            self::tributary('Commondale Beck', 'esk'),
            self::tributary('Danby Beck', 'esk'),
            self::tributary('Iburndale Beck', 'esk'),

            // Hull
            self::main('River Hull', 'Hull and East Riding', [53.74, -0.32]),
            self::tributary('Driffield Beck', 'hull'),
        ];
    }

    /**
     * The Thames from its source to the tidal limit, with the tributaries
     * that join above Teddington. Rivers joining below it (the Lea, Brent,
     * Wandle, Roding) are London's, in london(), which also lists the Thames.
     *
     * @return array<int, array{name: string, os_name?: string|null, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null, also_runs_through?: array<string, array<int, string>>|null}>
     */
    private static function thamesValley(): array
    {
        return [
            // Never clear the Thames's centreline: OS splits the tidal Thames into pieces the reader cannot rebuild (it runs
            // them backwards from Erith), so its stored line, source to London Bridge, came from an earlier reader.
            self::main('River Thames', 'Thames', [51.5055, -0.0880], ['River Isis', 'Seacourt Stream', 'Shill Brook'], alsoRunsThrough: ['london' => ['hampton-court', 'kingston-upon-thames', 'teddington-lock', 'richmond', 'kew', 'hammersmith', 'putney']]),
            self::tributary('River Churn', 'thames'),
            self::tributary('River Coln', 'thames'),
            self::tributary('River Leach', 'thames'),
            self::tributary('River Windrush', 'thames'),
            self::tributary('River Evenlode', 'thames'),
            self::tributary('River Cherwell', 'thames'),
            self::tributary('River Ray', 'cherwell'),
            self::tributary('River Ock', 'thames', ['Marcham Brook']),
            self::tributary('River Thame', 'thames'),
            self::tributary('River Pang', 'thames'),
            self::tributary('River Kennet', 'thames', ['Kennet and Avon Canal']),
            self::tributary('River Lambourn', 'kennet'),
            self::tributary('River Loddon', 'thames'),
            self::tributary('Blackwater River', 'loddon', ['River Blackwater'], slug: 'blackwater'),
            self::tributary('River Colne', 'thames'),
            self::tributary('River Misbourne', 'colne'),
            self::tributary('River Ver', 'colne'),
            self::tributary('River Gade', 'colne'),
            self::tributary('River Bulbourne', 'gade', ['River Bulborne']),
            self::tributary('River Chess', 'colne'),
            self::tributary('River Wey', 'thames'),
            self::tributary('Cranleigh Waters', 'wey', ['Cranleigh Waters/Littlemead Brook'], slug: 'cranleigh-waters'),
            self::tributary('River Mole', 'thames'),
            self::tributary('Hogsmill River', 'thames', ['River Hogsmill'], slug: 'hogsmill'),
        ];
    }

    /**
     * The main rivers of Lancashire and Cumbria that United Utilities
     * monitors, each with its tributaries. Mouths are at the head of each
     * estuary, as the tidal reaches are not walkable river.
     *
     * @return array<int, array{name: string, os_name?: string|null, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null, also_runs_through?: array<string, array<int, string>>|null}>
     */
    private static function northWest(): array
    {
        return [
            // Ribble
            self::main('River Ribble', 'Ribble', [53.7470, -2.7390]),
            self::tributary('River Calder', 'ribble'),
            self::tributary('Pendle Water', 'calder'),
            self::tributary('Colne Water', 'pendle-water'),
            self::tributary('Walverden Water', 'pendle-water'),
            self::tributary('Hyndburn Brook', 'calder'),
            self::tributary('Woodnook Water', 'hyndburn-brook'),
            self::tributary('River Darwen', 'ribble'),
            self::tributary('River Blakewater', 'darwen'),
            // Eaves Brook barely exists in OS Open Rivers, so its monitors count towards Savick Brook, which it joins in Preston.
            self::tributary('Savick Brook', 'ribble', ['Eaves Brook', 'Eaves Brook, a tributary of Savick Brook, a tributary of the River Ribble']),

            // Lune
            self::main('River Lune', 'Lune', [54.0480, -2.8150]),
            self::tributary('River Wenning', 'lune'),
            self::tributary('River Greta', 'lune'),
            self::tributary('Artle Beck', 'lune', ['Artle Beck a tributary of the River Lune']),

            // Kent
            self::main('River Kent', 'Kent', [54.2380, -2.7930]),

            // Eden
            self::main('River Eden', 'Eden', [54.9300, -3.0400]),
            self::tributary('River Eamont', 'eden'),
            self::tributary('River Irthing', 'eden'),
            self::tributary('Brampton Beck', 'irthing'),
            self::tributary('Milton Beck', 'irthing'),
            self::tributary('River Petteril', 'eden'),
            self::tributary('River Caldew', 'eden'),

            // Derwent: a second River Greta, which joins at Keswick, so it needs its own slug.
            self::main('River Derwent', 'Derwent', [54.6460, -3.5500]),
            self::tributary('River Greta', 'derwent', slug: 'greta-keswick'),
        ];
    }

    /**
     * The rivers Northumbrian Water monitors, from the Tees to the Tweed.
     * The North and South Tyne flow into the Tyne, so their overflows count
     * for Tyne spots, and the Till flows into the Tweed. The Tweed runs
     * mostly through Scotland, so its overflows come from Scottish Water's
     * feed as well as Northumbrian Water's. Tributaries that join below the
     * tidal limit (the Derwent, Team, Ouse Burn and Don on the Tyne) still
     * count on the river page.
     *
     * @return array<int, array{name: string, os_name?: string|null, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null, also_runs_through?: array<string, array<int, string>>|null}>
     */
    private static function northEast(): array
    {
        return [
            // Tyne
            self::main('River Tyne', 'Tyne', [55.0090, -1.4300]),
            self::tributary('River Derwent', 'tyne'),
            self::tributary('River Team', 'tyne'),
            self::tributary('Ouse Burn', 'tyne'),
            self::tributary('River Don', 'tyne'),
            self::main('River South Tyne', 'Tyne', [54.9880, -2.1430], flowsInto: 'tyne'),
            self::tributary('River Nent', 'south-tyne'),
            self::tributary('River Allen', 'south-tyne'),
            self::tributary('River East Allen', 'allen'),
            self::tributary('River West Allen', 'allen'),
            self::main('River North Tyne', 'Tyne', [54.9900, -2.1380], flowsInto: 'tyne'),
            self::tributary('River Rede', 'north-tyne'),

            // Wear
            self::main('River Wear', 'Wear', [54.9140, -1.3700]),
            self::tributary('Bedburn Beck', 'wear'),
            self::tributary('River Gaunless', 'wear'),
            self::tributary('River Browney', 'wear'),
            self::tributary('River Deerness', 'browney'),

            // Tees
            self::main('River Tees', 'Tees', [54.6300, -1.1500]),
            self::tributary('River Lune', 'tees', slug: 'lune-teesdale'),
            self::tributary('River Balder', 'tees'),
            self::tributary('River Greta', 'tees'),
            self::tributary('River Skerne', 'tees'),
            self::tributary('River Leven', 'tees'),
            self::tributary('Lustrum Beck', 'tees'),

            // Northumberland's coastal rivers
            self::main('River Coquet', 'Northumberland Coast', [55.3380, -1.5830]),
            self::main('River Aln', 'Northumberland Coast', [55.3870, -1.6150]),
            self::main('River Wansbeck', 'Northumberland Coast', [55.1720, -1.5480]),
            self::tributary('River Font', 'wansbeck'),
            self::main('River Blyth', 'Northumberland Coast', [55.1300, -1.5100]),
            self::tributary('River Pont', 'blyth'),

            // Tweed, with the Till flowing into it at Twizel
            self::main('River Tweed', 'Tweed', [55.7660, -2.0050], alsoRunsThrough: ['scotland' => ['peebles', 'innerleithen', 'walkerburn', 'melrose', 'dryburgh', 'kelso', 'coldstream', 'paxton']]),
            self::tributary('Biggar Water', 'tweed'),
            self::tributary('Lyne Water', 'tweed'),
            self::tributary('Eddleston Water', 'tweed'),
            self::tributary('Ettrick Water', 'tweed'),
            self::tributary('Yarrow Water', 'ettrick-water'),
            self::tributary('Leader Water', 'tweed'),
            self::tributary('River Teviot', 'tweed', ['Teviot Water']),
            self::tributary('Jed Water', 'teviot'),
            self::tributary('Ale Water', 'teviot'),
            self::tributary('Eden Water', 'tweed'),
            self::tributary('Leet Water', 'tweed'),
            self::tributary('Whiteadder Water', 'tweed'),
            self::tributary('Blackadder Water', 'whiteadder-water'),
            self::main('River Till', 'Tweed', [55.6860, -2.2140], flowsInto: 'tweed'),
            self::tributary('River Breamish', 'till'),
            self::tributary('Wooler Water', 'till'),
            self::tributary('River Glen', 'till'),
            self::tributary('Bowmont Water', 'glen'),
        ];
    }

    /**
     * Southern Water's rivers: Kent's Medway and Great Stour (to Plucks
     * Gutter; OS's lower River Stour misses Sandwich), Sussex's
     * Rother, Cuckmere, Ouse, Adur and Arun (with the Western Rother, which
     * shares OS's name with the Rother), Hampshire's Itchen, Test, Meon and
     * Lymington River, and the Isle of Wight's Medina and Eastern Yar.
     * Southern Water often writes a river bare ("MEDWAY", "ROTHER E").
     *
     * @return array<int, array{name: string, os_name?: string|null, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null, also_runs_through?: array<string, array<int, string>>|null}>
     */
    private static function southEast(): array
    {
        return [
            // Medway, from the Weald through Tonbridge and Maidstone to its estuary
            self::main('River Medway', 'Medway', [51.4400, 0.7300], ['River Medway Estuary', 'Medway Estuary']),
            self::tributary('River Eden', 'medway'),
            self::tributary('Hilden Brook', 'medway'),
            self::tributary('River Teise', 'medway'),
            self::tributary('River Beult', 'medway'),
            self::tributary('River Len', 'medway'),

            // Great Stour, through Ashford and Canterbury to Plucks Gutter, below which OS calls it the River Stour
            self::main('Great Stour', 'Stour', [51.3270, 1.2700], ['Stour']),
            self::tributary('East Stour River', 'great-stour', ['East Stour']),

            // Sussex
            self::main('River Rother', 'Sussex rivers', [50.9350, 0.7700], ['Rother', 'Rother E', 'Lower Rother', 'Eastern Rother']),
            self::tributary('River Dudwell', 'rother'),
            self::tributary('Kent Ditch', 'rother'),
            self::tributary('Hexden Channel', 'rother'),
            self::tributary('River Tillingham', 'rother'),
            self::tributary('River Brede', 'rother'),
            self::main('Cuckmere River', 'Sussex rivers', [50.7630, 0.1520], ['River Cuckmere', 'Cuckmere'], slug: 'cuckmere'),
            self::main('River Ouse', 'Sussex rivers', [50.7850, 0.0570], ['Sussex Ouse']),
            self::tributary('River Uck', 'ouse'),
            self::tributary('Framfield Stream', 'uck'),
            self::tributary('Glynde Reach', 'ouse', ['Glynde Reach/ River Ouse']),
            self::tributary('Cockhaise Brook', 'ouse', ['Lywood Stream']),
            self::tributary('Scrase Stream', 'ouse', ['Scrase Bridge Stream']),
            self::main('River Adur', 'Sussex rivers', [50.8320, -0.2650]),
            self::main('River Arun', 'Sussex rivers', [50.8050, -0.5450]),
            self::tributary('River Rother', 'arun', ['Western Rother', 'Rother W', 'Rother'], slug: 'western-rother'),
            self::tributary('River Kird', 'arun'),
            self::tributary('River Stor', 'arun'),

            // Hampshire's chalk streams and the New Forest
            self::main('River Itchen', 'Hampshire rivers', [50.8980, -1.3880]),
            self::tributary('River Alre', 'itchen', ['River Arle']),
            self::tributary('Monks Brook', 'itchen'),
            self::main('River Test', 'Hampshire rivers', [50.9180, -1.4700]),
            self::tributary('River Anton', 'test'),
            self::tributary('River Dever', 'test'),
            self::tributary('Bourne Rivulet', 'test'),
            self::tributary('Wallop Brook', 'test'),
            self::tributary('Tadburn Lake', 'test'),
            self::tributary('Bartley Water', 'test'),
            self::main('River Meon', 'Hampshire rivers', [50.8180, -1.2350], ['Shedfield Stream']),
            self::main('Lymington River', 'Hampshire rivers', [50.7500, -1.5300], ['Lymington'], slug: 'lymington'),

            // Isle of Wight
            self::main('River Medina', 'Isle of Wight', [50.7620, -1.2950]),
            self::main('Eastern Yar', 'Isle of Wight', [50.6880, -1.0950], ['Yar'], osName: 'River Yar'),
        ];
    }

    /**
     * Wessex Water's rivers: the Bristol Avon, with Somerset's Frome flowing
     * into it, the Somerset Levels' Parrett (with the Tone) and Brue, Dorset's
     * Stour, Frome and Piddle, and the Hampshire Avon. Two Avons and three
     * Fromes share OS's names, so the site names them as people do (the
     * Bristol Avon) and keeps OS's as osName; Bristol's Frome, a tributary
     * joining the same Avon as Somerset's, takes a mouth hint.
     *
     * @return array<int, array{name: string, os_name?: string|null, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null, also_runs_through?: array<string, array<int, string>>|null}>
     */
    private static function wessex(): array
    {
        return [
            // Bristol Avon, from Malmesbury through Bath to Avonmouth. Brooks OS does not map (the Malago, Clackers, Coombe,
            // Fishponds, Dodham, Halse and Crewkerne ones) are aliases of the river they run into.
            self::main('Bristol Avon', 'Bristol Avon', [51.5070, -2.7050], ['River Bristol Avon', 'Sherston Avon', 'Malago', 'Clackers Brook'], osName: 'River Avon'),
            self::tributary('River Frome', 'bristol-avon', ['Coombe Brook', 'Fishponds Brook'], slug: 'frome-bristol', mouth: [51.4510, -2.5960]),
            self::tributary('River Trym', 'bristol-avon'),
            self::tributary('Brislington Brook', 'bristol-avon'),
            self::tributary('Siston Brook', 'bristol-avon'),
            self::tributary('Warmley Brook', 'siston-brook'),
            self::tributary('River Boyd', 'bristol-avon'),
            self::tributary('River Chew', 'bristol-avon'),
            self::tributary('Lam Brook', 'bristol-avon'),
            self::tributary('Midford Brook', 'bristol-avon'),
            self::tributary('Cam Brook', 'midford-brook'),
            self::tributary('Wellow Brook', 'midford-brook'),
            self::tributary('By Brook', 'bristol-avon'),
            self::tributary('River Biss', 'bristol-avon'),
            self::tributary('Bitham Brook', 'biss'),
            self::tributary('Semington Brook', 'bristol-avon'),
            self::tributary('Summerham Brook', 'semington-brook'),
            self::tributary('River Marden', 'bristol-avon'),
            self::main('Somerset Frome', 'Bristol Avon', [51.3380, -2.3030], flowsInto: 'bristol-avon', osName: 'River Frome'),
            self::tributary('Mells River', 'somerset-frome'),
            // OS's Mells River is a short stub; Wessex Water's "Mells River" overflows are on what OS calls Mells Stream.
            self::tributary('Mells Stream', 'mells-river', ['Mells River']),
            self::tributary('Nunney Brook', 'somerset-frome'),

            // Somerset Levels: the Parrett, with the Tone from Taunton, and the Brue
            self::main('River Parrett', 'Somerset Levels', [51.2100, -3.0200], ['Crewkerne Stream']),
            self::tributary('River Isle', 'parrett'),
            self::tributary('Fivehead River', 'isle'),
            self::tributary('River Yeo', 'parrett', ['Dodham Brook']),
            self::tributary('River Cam', 'yeo'),
            self::tributary('Chinnock Brook', 'parrett'),
            self::main('River Tone', 'Somerset Levels', [51.0700, -2.9130], ['Hillfarrance Brook', 'Halse Water'], flowsInto: 'parrett'),
            self::main('River Brue', 'Somerset Levels', [51.2180, -2.9920]),
            self::tributary('River Sheppey', 'brue'),
            self::tributary('River Alham', 'brue'),

            // Dorset: the Stour to Christchurch Harbour, and the Frome and Piddle to Poole Harbour
            self::main('River Stour', 'Dorset rivers', [50.7280, -1.7700], ['Stour']),
            self::tributary('Shreen Water', 'stour'),
            self::tributary('River Cale', 'stour'),
            self::tributary('River Lydden', 'stour'),
            self::tributary('Caundle Brook', 'lydden'),
            self::tributary('River Allen', 'stour'),
            self::tributary('Moors River', 'stour'),
            self::tributary('Uddens Water', 'moors-river'),
            self::main('Dorset Frome', 'Dorset rivers', [50.6870, -2.0900], osName: 'River Frome'),
            self::tributary('River Hooke', 'dorset-frome'),
            self::tributary('Sydling Water', 'dorset-frome'),
            self::tributary('River Cerne', 'dorset-frome'),
            self::tributary('River Win', 'dorset-frome'),
            self::main('River Piddle', 'Dorset rivers', [50.6930, -2.0950]),

            // Hampshire Avon, from the Vale of Pewsey through Salisbury to Christchurch
            self::main('Hampshire Avon', 'Hampshire Avon', [50.7330, -1.7720], ['Hampshire Avon River', 'Hampshire Avon, River'], alsoRunsThrough: ['south-east' => ['fordingbridge', 'ringwood']], osName: 'River Avon'),
            self::tributary('River Nadder', 'hampshire-avon'),
            self::tributary('River Wylye', 'nadder'),
            self::tributary('River Bourne', 'hampshire-avon'),
            self::tributary('River Ebble', 'hampshire-avon'),
        ];
    }

    /**
     * Thames Water's London rivers, each joining the tidal Thames below
     * Teddington, so none flows into a Thames Valley spot: the Lea from
     * Hertfordshire (OS's "River Lea", Thames Water's "River Lee"), the
     * Roding from Essex, the Brent and Crane in the west, the Wandle, Beverley
     * Brook and Ravensbourne in the south, and Kent's Darent with the Cray.
     *
     * @return array<int, array{name: string, os_name?: string|null, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null, also_runs_through?: array<string, array<int, string>>|null}>
     */
    private static function london(): array
    {
        return [
            // Lea, from Luton to Bow Creek. Brooks OS does not map (Dagenham, Stonebridge, Mitchell, Decoy, Edgware and
            // the Roding's) are aliases of the river they run into, as their overflows sit within a kilometre or so of it.
            self::main('River Lea', 'Lea', [51.5085, 0.0090], ['River Lee', 'River Lee Navigation', 'Lee Navigation', 'Bow Creek', 'Dagenham Brook', 'Stonebridge Brook']),
            self::tributary('River Mimram', 'lea'),
            self::tributary('River Beane', 'lea'),
            self::tributary('River Rib', 'lea'),
            self::tributary('River Ash', 'lea'),
            self::tributary('River Stort', 'lea', ['Stort Navigation', 'River Stort Navigation']),
            self::tributary('Pincey Brook', 'stort'),
            self::tributary('Turkey Brook', 'lea'),
            self::tributary('The Ching', 'lea', ['Ching Brook'], slug: 'ching'),
            self::tributary("Pymme's Brook", 'lea', ['Pymmes Brook'], slug: 'pymmes-brook'),
            self::tributary("Salmon's Brook", 'pymmes-brook', ['Salmon Brook', 'Salmons Brook'], slug: 'salmons-brook'),
            self::tributary('Bounds Green Brook', 'pymmes-brook'),

            // Roding, from Essex to Barking Creek
            self::main('River Roding', 'Roding', [51.5150, 0.0950], ['Lower Roding', 'Barking Creek', 'Brookhouse Brook', 'Chigwell Brook']),
            self::tributary('Cripsey Brook', 'roding'),
            self::tributary('Loxford Water', 'roding'),
            self::tributary('Seven Kings Water', 'roding'),

            // West London
            self::main('River Brent', 'West London rivers', [51.4850, -0.3050], ['Mitchell Brook', 'Decoy Brook']),
            self::tributary('Dollis Brook', 'brent'),
            self::tributary('Silk Stream', 'brent', ['Edgware Brook']),
            self::tributary('Wealdstone Brook', 'brent'),
            self::main('River Crane', 'West London rivers', [51.4720, -0.3230]),
            self::tributary('Yeading Brook', 'crane'),

            // South London
            self::main('River Wandle', 'South London rivers', [51.4620, -0.1930]),
            self::main('Beverley Brook', 'South London rivers', [51.4690, -0.2260], slug: 'beverley-brook'),
            self::main('Ravensbourne River', 'South London rivers', [51.4820, -0.0180], ['River Ravensbourne', 'River Ravensbourne / Deptford Creek'], slug: 'ravensbourne'),
            self::tributary('Quaggy River', 'ravensbourne', ['River Quaggy', 'Quaggy'], slug: 'quaggy'),
            self::tributary('Kyd Brook', 'quaggy'),
            self::tributary('Pool River', 'ravensbourne'),

            // Darent, from the Kent downs to Dartford Creek
            self::main('River Darent', 'Darent', [51.4800, 0.2100], ['Dartford Creek', 'Darent / Dartford Creek']),
            self::tributary('River Cray', 'darent', ['Crayford Creek']),
        ];
    }

    /**
     * South West Water's rivers, Devon and Cornwall: the Dartmoor rivers
     * (Dart, Teign, Tavy, Plym, Erme, Avon), the Exe with the Barle, the
     * Tamar, the Cornish Fowey and Camel, the Taw and Torridge, the Otter and
     * Axe and the East Lyn. OS starts the Dart at Dartmeet, so the East Dart
     * keeps its own pages and flows into it. The Avon shares its name with
     * rivers across England, so its mouth hint matters.
     *
     * @return array<int, array{name: string, os_name?: string|null, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null, also_runs_through?: array<string, array<int, string>>|null}>
     */
    private static function southWest(): array
    {
        return [
            // Dartmoor rivers
            self::main('River Dart', 'Dartmoor rivers', [50.3450, -3.5700], ['River Dart Estuary', 'Dart Estuary', 'Dart River Estuary']),
            self::tributary('Bidwell Brook', 'dart'),
            self::tributary('West Dart River', 'dart'),
            self::tributary('Blackbrook River', 'west-dart-river', ['Black Brook River']),
            self::tributary('River Webburn', 'dart'),
            self::tributary('East Webburn River', 'webburn'),
            self::tributary('West Webburn River', 'webburn'),
            self::tributary('River Mardle', 'dart'),
            self::tributary('River Ashburn', 'dart'),
            self::main('East Dart River', 'Dartmoor rivers', [50.5400, -3.8800], ['East Dart'], flowsInto: 'dart', slug: 'east-dart'),
            self::main('River Teign', 'Dartmoor rivers', [50.5450, -3.5000], ['Teign Estuary', 'River Teign Estuary']),
            self::tributary('Aller Brook', 'teign'),
            self::tributary('North Teign River', 'teign'),
            self::tributary('South Teign River', 'teign'),
            self::tributary('River Bovey', 'teign'),
            self::tributary('Wray Brook', 'bovey'),
            self::tributary('River Lemon', 'teign'),
            self::main('River Plym', 'Dartmoor rivers', [50.3650, -4.1200], ['River Plym Estuary', 'Plym Estuary']),
            self::tributary('River Meavy', 'plym'),
            self::main('River Erme', 'Dartmoor rivers', [50.3100, -3.9500], ['River Erm']),
            self::main('River Avon', 'Dartmoor rivers', [50.2800, -3.8800], ['River Avon Estuary']),

            // Exe, with the Barle from Exmoor
            self::main('River Exe', 'Exe', [50.6150, -3.4250], ['Exe Estuary', 'River Exe Estuary']),
            self::tributary('River Lowman', 'exe'),
            self::tributary('River Haddeo', 'exe'),
            self::tributary('River Batherm', 'exe'),
            self::tributary('River Culm', 'exe'),
            self::tributary('River Creedy', 'exe'),
            // Four River Yeos: Crediton's and Bideford's, each nearest its own parent, and Barnstaple's and Lapford's, which
            // both join the Taw, so they take mouth hints.
            self::tributary('River Yeo', 'creedy', slug: 'yeo-creedy'),
            self::tributary('River Clyst', 'exe'),
            self::main('River Barle', 'Exe', [51.0100, -3.5400], flowsInto: 'exe'),

            // Tamar, with the Tavy flowing into its estuary
            self::main('River Tamar', 'Tamar', [50.3700, -4.1900], ['River Tamar Estuary', 'Tamar Estuary']),
            self::tributary('River Deer', 'tamar'),
            self::tributary('River Ottery', 'tamar'),
            self::tributary('River Carey', 'tamar'),
            self::tributary('River Kensey', 'tamar'),
            self::tributary('River Lyd', 'tamar'),
            self::tributary('River Thrushel', 'lyd'),
            self::tributary('River Wolf', 'thrushel'),
            self::tributary('River Inny', 'tamar'),
            self::tributary('River Lynher', 'tamar', ['River Lyner', 'River Lynher Estuary']),
            self::tributary('River Tiddy', 'lynher', ['Tiddy Brook']),
            self::main('River Tavy', 'Tamar', [50.4400, -4.1900], flowsInto: 'tamar'),
            self::tributary('River Walkham', 'tavy'),

            // Cornwall
            self::main('River Fowey', 'Cornish rivers', [50.3350, -4.6400], ['River Fowey Estuary', 'Fowey Estuary']),
            self::tributary('St Neot River', 'fowey'),
            self::main('River Camel', 'Cornish rivers', [50.5500, -4.9300], ['Camel Estuary', 'River Camel Estuary']),
            self::tributary('De Lank River', 'camel'),
            self::tributary('River Allen', 'camel'),

            // North Devon: the Taw and Torridge share an estuary
            self::main('River Taw', 'North Devon rivers', [51.0700, -4.1500], ['River Taw Estuary', 'Taw Estuary']),
            self::tributary('Little Dart River', 'taw', ['Little Dart']),
            self::tributary('River Mole', 'taw'),
            self::tributary('River Bray', 'mole'),
            self::tributary('River Yeo', 'taw', slug: 'yeo-taw', mouth: [51.0810, -4.0670]),
            self::tributary('River Yeo', 'taw', slug: 'yeo-lapford', mouth: [50.8700, -3.8150]),
            self::main('River Torridge', 'North Devon rivers', [51.0500, -4.2000], ['River Torridge Estuary', 'Torridge Estuary']),
            self::tributary('River Waldon', 'torridge'),
            self::tributary('River Lew', 'torridge'),
            self::tributary('River Yeo', 'torridge', slug: 'yeo-torridge'),
            self::tributary('River Okement', 'torridge'),
            self::tributary('East Okement River', 'okement'),
            self::tributary('West Okement River', 'okement', ['West Okement']),
            self::main('East Lyn River', 'North Devon rivers', [51.2320, -3.8280], ['East Lyn'], slug: 'east-lyn'),
            self::tributary('Hoaroak Water', 'east-lyn'),
            self::tributary('West Lyn River', 'east-lyn'),

            // East Devon
            self::main('River Otter', 'East Devon rivers', [50.6300, -3.3000]),
            self::tributary('River Tale', 'otter'),
            self::main('River Axe', 'East Devon rivers', [50.7000, -3.0700], ['River Axe Estuary']),
            self::tributary('River Yarty', 'axe'),
            self::tributary('River Coly', 'axe'),
        ];
    }

    /**
     * Anglian Water's rivers, Lincolnshire to Essex: the Great Ouse and the
     * rivers into the Wash, the Broads and the Suffolk and Essex rivers.
     * Several share names with rivers elsewhere (Stour, Colne, Blackwater,
     * Glen), so each main river's mouth hint matters.
     *
     * @return array<int, array{name: string, os_name?: string|null, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null, also_runs_through?: array<string, array<int, string>>|null}>
     */
    private static function eastOfEngland(): array
    {
        return [
            // Great Ouse, with the Cam, Lark and Little Ouse flowing into it. The Wissey and Nar only add their
            // overflows: OS splits the lower Wissey from the rest where it crosses the Cut-off Channel.
            self::main('River Great Ouse', 'Great Ouse', [52.7600, 0.3900], ['Great Ouse', 'Great River Ouse', 'River Ouse']),
            self::tributary('River Tove', 'great-ouse'),
            self::tributary('River Ouzel', 'great-ouse'),
            self::tributary('River Ivel', 'great-ouse'),
            self::tributary('River Hiz', 'ivel'),
            self::tributary('River Flit', 'ivel'),
            self::tributary('River Kym', 'great-ouse'),
            self::tributary('River Wissey', 'great-ouse'),
            self::tributary('River Nar', 'great-ouse'),
            self::tributary('Gaywood River', 'great-ouse'),
            self::main('River Cam', 'Great Ouse', [52.3520, 0.2310], ['River Rhee'], flowsInto: 'great-ouse'),
            self::tributary('River Granta', 'cam'),
            self::main('River Lark', 'Great Ouse', [52.4300, 0.3300], flowsInto: 'great-ouse'),
            self::tributary('River Linnet', 'lark'),
            self::tributary('River Kennett', 'lark'),
            self::main('Little Ouse River', 'Great Ouse', [52.5000, 0.3600], ['River Little Ouse', 'Little Ouse'], flowsInto: 'great-ouse', slug: 'little-ouse'),
            self::tributary('River Thet', 'little-ouse'),
            self::tributary('The Black Bourn', 'little-ouse', ['Black Bourn'], slug: 'black-bourn'),

            // Nene, Welland and Witham, into the Wash
            self::main('River Nene', 'Nene', [52.8400, 0.2100]),
            self::tributary('Bugbrooke Brook', 'nene'),
            self::tributary('Grendon Brook', 'nene'),
            self::tributary('River Ise', 'nene'),
            self::tributary('Willow Brook', 'nene'),
            self::tributary('River Nene Brampton Branch', 'nene', ['Brampton Branch R.Nene'], slug: 'nene-brampton-branch'),
            self::main('River Welland', 'Welland', [52.8700, 0.0400]),
            self::tributary('Langton Brook', 'welland'),
            self::tributary('River Gwash', 'welland'),
            self::tributary('River Chater', 'welland'),
            self::tributary('River Glen', 'welland'),
            self::main('River Witham', 'Witham', [52.9800, -0.0200]),
            self::tributary('River Brant', 'witham'),
            self::tributary('River Bain', 'witham', ['Old River Bain']),
            self::tributary('River Slea', 'witham'),

            // The Broads: the Wensum, Bure and Waveney flow into the Yare
            self::main('River Yare', 'Broads', [52.5750, 1.7350]),
            self::tributary('River Tas', 'yare'),
            self::tributary('River Chet', 'yare'),
            self::main('River Wensum', 'Broads', [52.6200, 1.3200], flowsInto: 'yare'),
            self::main('River Bure', 'Broads', [52.6100, 1.7200], flowsInto: 'yare'),
            self::tributary('River Ant', 'bure'),
            self::tributary('River Thurne', 'bure'),
            self::main('River Waveney', 'Broads', [52.5800, 1.6600], flowsInto: 'yare'),

            // Suffolk, with the Stour on the Essex border
            self::main('River Stour', 'Suffolk rivers', [51.9500, 1.2700]),
            self::tributary('River Glem', 'stour'),
            self::tributary('River Box', 'stour'),
            self::tributary('River Brett', 'stour'),
            self::main('River Gipping', 'Suffolk rivers', [52.0500, 1.1520], ['River Orwell']),
            self::main('River Deben', 'Suffolk rivers', [51.9900, 1.3900]),
            self::main('River Alde', 'Suffolk rivers', [52.1500, 1.5700], ['River Ore']),

            // Essex: the Chelmer flows into the Blackwater at Maldon
            self::main('River Colne', 'Essex rivers', [51.8000, 1.0200], ['Colne Estuary']),
            self::tributary('Salary Brook', 'colne'),
            self::main('River Blackwater', 'Essex rivers', [51.7350, 0.7000], ['River Blackwtaer']),
            self::tributary('River Pant', 'blackwater'),
            self::tributary('River Brain', 'blackwater'),
            self::main('River Chelmer', 'Essex rivers', [51.7300, 0.6900], flowsInto: 'blackwater'),
            self::tributary('River Can', 'chelmer'),
            self::tributary('River Wid', 'can'),
            self::tributary('River Ter', 'chelmer'),
            self::tributary('Sandon Brook', 'chelmer'),
            self::main('River Crouch', 'Essex rivers', [51.6250, 0.8800], ['Upper River Crouch']),
        ];
    }

    /**
     * Severn Trent's rivers: the Peak District rivers and the Soar flowing
     * into the Trent, and the Teme and Warwickshire Avon into the Severn.
     * OS names the Severn and Teme in Welsh first; the reader matches their
     * English names as alternatives. Overflows on the Welsh upper Severn
     * belong to Hafren Dyfrdwy, which is not on Stream, so they do not
     * count yet.
     *
     * @return array<int, array{name: string, os_name?: string|null, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null, also_runs_through?: array<string, array<int, string>>|null}>
     */
    private static function midlands(): array
    {
        return [
            // Trent
            self::main('River Trent', 'Trent', [53.7000, -0.6900]),
            self::tributary('River Tame', 'trent'),
            self::tributary('River Rea', 'tame'),
            self::tributary('River Cole', 'tame'),
            self::tributary('Ford Brook', 'tame'),
            self::tributary('River Anker', 'tame'),
            self::tributary('River Blythe', 'tame'),
            self::tributary('River Sow', 'trent'),
            self::tributary('River Penk', 'sow', ['River Penk/Wergs Brook']),
            self::tributary('River Erewash', 'trent'),
            self::tributary('River Leen', 'trent'),
            self::tributary('River Devon', 'trent'),
            self::tributary('River Idle', 'trent'),
            self::tributary('River Maun', 'idle'),
            self::tributary('River Meden', 'idle'),
            self::tributary('River Poulter', 'idle'),
            self::tributary('River Ryton', 'idle'),
            self::main('River Soar', 'Trent', [52.8640, -1.2620], ['River Soar Or Tribs', 'Various Sites River Soar'], flowsInto: 'trent'),
            self::tributary('River Wreake', 'soar', ['River Wreake Trib']),
            self::tributary('River Sence', 'soar'),

            // Peak District rivers, all flowing into the Trent
            self::main('River Derwent', 'Peak District', [52.8700, -1.3700], flowsInto: 'trent'),
            self::tributary('River Noe', 'derwent'),
            self::tributary('River Amber', 'derwent'),
            self::tributary('River Ecclesbourne', 'derwent'),
            self::tributary('Markeaton Brook', 'derwent'),
            self::main('Derbyshire Wye', 'Peak District', [53.1880, -1.6160], flowsInto: 'derwent', slug: 'wye', osName: 'River Wye'),
            self::tributary('River Lathkill', 'wye'),
            self::tributary('River Bradford', 'lathkill'),
            self::main('River Dove', 'Peak District', [52.8300, -1.5800], flowsInto: 'trent'),
            self::tributary('River Churnet', 'dove'),
            self::tributary('River Tean', 'dove'),
            self::tributary('Henmore Brook', 'dove'),
            self::main('River Manifold', 'Peak District', [53.0480, -1.7830], flowsInto: 'dove'),
            self::tributary('River Hamps', 'manifold'),

            // Severn
            self::main('River Severn', 'Severn', [51.8700, -2.2700]),
            self::tributary('River Tern', 'severn'),
            self::tributary('River Roden', 'tern'),
            self::tributary('Rea Brook', 'severn'),
            self::tributary('River Stour', 'severn'),
            self::tributary('Smestow Brook', 'stour'),
            self::main('River Teme', 'Severn', [52.1700, -2.2200], flowsInto: 'severn'),
            self::tributary('River Clun', 'teme'),
            self::tributary('River Onny', 'teme'),
            self::tributary('River Corve', 'teme'),
            self::main('River Avon', 'Severn', [51.9930, -2.1600], flowsInto: 'severn'),
            self::tributary('River Leam', 'avon'),
            self::tributary('River Arrow', 'avon'),
            self::tributary('River Alne', 'arrow'),
            self::tributary('River Isbourne', 'avon'),
            self::tributary('River Swift', 'avon'),
            self::tributary('River Sowe', 'avon'),
            self::tributary('River Stour', 'avon', slug: 'stour-avon'),
        ];
    }

    /**
     * Scottish Water's rivers. Several share names with English rivers (Don,
     * Tyne, Esk, Eden, Leven) or other Scottish ones (Dee, Esk, Almond, Garry, Isla),
     * so each main river's mouth picks the right one, and monitors only join
     * a river within reach of its centreline. Rivers with spots are main
     * rivers flowing into the river they join, so they keep their own pages.
     *
     * @return array<int, array{name: string, os_name?: string|null, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null, also_runs_through?: array<string, array<int, string>>|null}>
     */
    private static function scotland(): array
    {
        return [
            // Tay
            self::main('River Tay', 'Tay', [56.3550, -3.3300]),
            self::tributary('Craigie Burn', 'tay'),
            self::tributary('River Isla', 'tay'),
            self::tributary('River Ericht', 'isla'),
            self::tributary('Alyth Burn', 'isla'),
            self::main('River Tummel', 'Tay', [56.6520, -3.6600], flowsInto: 'tay'),
            self::main('River Garry', 'Tay', [56.7060, -3.7720], flowsInto: 'tummel'),
            self::main('River Braan', 'Tay', [56.5620, -3.5880], flowsInto: 'tay'),
            self::main('River Earn', 'Tay', [56.3550, -3.3100], flowsInto: 'tay'),

            // Spey and Ness
            self::main('River Spey', 'Highlands and Moray', [57.6670, -3.0980]),
            self::tributary('River Dulnain', 'spey'),
            self::tributary('River Nethy', 'spey'),
            self::main('River Ness', 'Highlands and Moray', [57.4930, -4.2320]),
            self::main('River Findhorn', 'Highlands and Moray', [57.6400, -3.6000]),
            self::main('River Lossie', 'Highlands and Moray', [57.7200, -3.2800]),
            self::tributary('Lhanbryd Burn', 'lossie'),

            // Dee and Don
            self::main('Aberdeenshire Dee', 'Aberdeenshire', [57.1420, -2.0700], slug: 'dee', osName: 'River Dee'),
            self::tributary('Water of Feugh', 'dee'),
            self::main('River Don', 'Aberdeenshire', [57.1760, -2.0830]),

            // Forth
            self::main('River Forth', 'Forth', [56.1100, -3.7900]),
            self::tributary('Bannock Burn', 'forth'),
            self::main('River Teith', 'Forth', [56.1530, -3.9590], flowsInto: 'forth'),
            self::main('Allan Water', 'Forth', [56.1350, -3.9420], flowsInto: 'forth'),
            self::main('River Devon', 'Forth', [56.1180, -3.8130], flowsInto: 'forth'),

            // Lothian
            self::main('Water of Leith', 'Lothian', [55.9770, -3.1700]),
            self::main('River Almond', 'Lothian', [55.9780, -3.3020]),
            self::tributary('Brox Burn', 'almond'),
            self::tributary('Breich Water', 'almond'),
            self::tributary('Niddry Burn', 'almond'),
            self::main('River Esk', 'Lothian', [55.9470, -3.0580]),
            self::tributary('River North Esk', 'esk', slug: 'north-esk-lothian'),
            self::tributary('River South Esk', 'esk', slug: 'south-esk-lothian'),
            self::main('River Tyne', 'Lothian', [56.0050, -2.5850]),
            self::tributary('Gifford Water', 'tyne'),

            // Clyde
            self::main('River Clyde', 'Clyde', [55.9600, -4.7800], ['Clyde Estuary']),
            self::tributary('Douglas Water', 'clyde'),
            self::tributary('Mouse Water', 'clyde'),
            self::tributary('River Nethan', 'clyde'),
            self::tributary('Avon Water', 'clyde'),
            self::tributary('Cadzow Burn', 'clyde'),
            self::tributary('South Calder Water', 'clyde', ['South Calder']),
            self::tributary('North Calder Water', 'clyde'),
            self::tributary('Rotten Calder', 'clyde', ['Rotten Calder Water']),
            self::tributary('Tollcross Burn', 'clyde'),
            self::main('River Kelvin', 'Clyde', [55.8690, -4.3100], flowsInto: 'clyde'),
            self::tributary('Luggie Water', 'kelvin'),
            self::tributary('Allander Water', 'kelvin'),
            self::main('White Cart Water', 'Clyde', [55.8870, -4.4300], ['White Cart Estuary'], flowsInto: 'clyde'),
            self::tributary('Black Cart Water', 'white-cart-water'),
            self::tributary('Levern Water', 'white-cart-water'),
            self::tributary('Auldhouse Burn', 'white-cart-water'),
            self::main('River Leven', 'Clyde', [55.9380, -4.5660]),

            // Ayrshire
            self::main('River Irvine', 'Ayrshire', [55.6050, -4.6900]),
            self::tributary('River Garnock', 'irvine'),
            self::tributary('Annick Water', 'irvine'),
            self::tributary('Kilmarnock Water', 'irvine'),
            self::tributary('Carmel Water', 'irvine'),
            self::main('River Ayr', 'Ayrshire', [55.4690, -4.6370]),
            self::tributary('Lugar Water', 'ayr'),
            self::main('River Doon', 'Ayrshire', [55.4320, -4.6480]),

            // Fife and Angus
            self::main('River Eden', 'Fife and Angus', [56.3600, -2.8800], ['Eden Estuary']),
            self::tributary('Lady Burn', 'eden'),
            self::tributary('Freuchie Burn', 'eden'),
            self::main('River South Esk', 'Fife and Angus', [56.7050, -2.4700], ['South Esk Estuary']),
            self::main('River North Esk', 'Fife and Angus', [56.7550, -2.4300]),
            self::tributary('Luther Water', 'north-esk'),

            // Dumfries and Galloway
            self::main('River Nith', 'Dumfries and Galloway', [55.0000, -3.5700]),
            self::main('River Annan', 'Dumfries and Galloway', [54.9750, -3.2620]),
            self::main('Galloway Dee', 'Dumfries and Galloway', [54.8300, -4.0500], slug: 'dee-galloway', osName: 'River Dee'),
        ];
    }

    /**
     * Welsh rivers, most of them Dŵr Cymru Welsh Water's. Names follow the
     * OS: its English alternative where it has one (as for the Severn), its
     * Welsh name where that is the only one (Afon Teifi); the other form is
     * an alias, since overflow feeds use either. The Wye and Dee run into
     * England, which keeps their English stretches here, as the Tweed is in
     * the North East. The Cardiff Taff and the Carmarthenshire Taf share the
     * Welsh name Afon Taf, so the mouths matter.
     *
     * @return array<int, array{name: string, os_name?: string|null, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null, also_runs_through?: array<string, array<int, string>>|null}>
     */
    private static function wales(): array
    {
        return [
            // South East Wales
            // Welsh Water writes the Rhondda Fawr several ways, and the Lugg as "Afon Lliw" like the Llugwy.
            self::main('River Taff', 'South East Wales', [51.4640, -3.1690], ['Afon Taf']),
            self::tributary('Afon Rhondda', 'taff', ['Rhondda River', 'River Rhondda', 'Rhondda Fawr River', 'Rhondda Fawr', 'River Rhondda Fawr', 'Rhondda River Fawr']),
            self::tributary('Afon Rhondda Fach', 'rhondda', ['Rhondda Fach River', 'Rhondda Fach', 'River Rhondda Fach'], slug: 'rhondda-fach'),
            self::tributary('Afon Cynon', 'taff', ['River Cynon']),
            self::tributary('Bargod Taf', 'taff'),
            self::tributary('Nant Morlais', 'taff', ['Morlais Brook']),
            self::tributary('Nant Clydach', 'taff'),
            self::main('River Usk', 'South East Wales', [51.5450, -2.9750], ['Afon Wysg', 'Usk Estuary', 'River Usk Estuary']),
            self::tributary('Afon Lwyd', 'usk'),

            // Wye
            self::main('River Wye', 'Wye', [51.6100, -2.6600], ['Afon Gwy', 'River Wye Estuary', 'Wye Estuary'], alsoRunsThrough: ['midlands' => ['hereford', 'ross-on-wye', 'symonds-yat']]),
            self::tributary('Irfon', 'wye', ['Afon Irfon', 'River Irfon']),
            self::tributary('River Ithon', 'wye', ['Afon Ieithon']),
            self::tributary('Afon Llynfi', 'wye', ['Llynfi River', 'River Llynfi']),
            self::tributary('River Lugg', 'wye', ['Afon Lliw', 'River Llugwy', 'Lugg Rheay']),
            self::tributary('River Monnow', 'wye', ['Afon Mynwy']),
            self::tributary('Yazor Brook', 'wye'),
            self::tributary('Rudhall Brook', 'wye'),

            // South West Wales
            self::main('River Tawe', 'South West Wales', [51.6150, -3.9250], ['Afon Tawe']),
            self::tributary('Afon Twrch', 'tawe', ['River Twrch'], slug: 'twrch'),
            self::main('River Neath', 'South West Wales', [51.6050, -3.8400], ['Afon Nedd', 'Neath Estuary', 'Estuarial Waters of Afon Nedd']),
            self::main('Afon Mellte', 'South West Wales', [51.7580, -3.5960], ['River Mellte'], flowsInto: 'neath'),
            self::tributary('Afon Hepste', 'mellte'),
            self::main('River Towy', 'South West Wales', [51.7700, -4.3750], ['Afon Tywi']),
            self::tributary('Afon Bran', 'towy', ['Afon Brân'], slug: 'bran'),
            self::tributary('Afon Gwenlais', 'towy', ['River Gwenlais'], slug: 'gwenlais'),
            self::main('Afon Teifi', 'South West Wales', [52.0950, -4.6850], ['River Teifi']),
            self::main('Western Cleddau', 'South West Wales', [51.7900, -4.9600], ['Cleddau Wen', 'Waters of the Western Cleddau']),

            // Mid and North Wales
            self::main('River Dovey', 'Mid and North Wales', [52.5600, -3.9500], ['Afon Dyfi']),
            self::main('Afon Mawddach', 'Mid and North Wales', [52.7500, -3.9400], ['River Mawddach']),
            self::main('Afon Wnion', 'Mid and North Wales', [52.7500, -3.9000], ['River Wnion'], flowsInto: 'mawddach'),
            self::main('Afon Glaslyn', 'Mid and North Wales', [52.9200, -4.1200], ['River Glaslyn', 'Glaslyn Estuary']),
            self::main('River Conwy', 'Mid and North Wales', [53.2800, -3.8300], ['Afon Conwy', 'Conwy Estuary']),
            self::main('Afon Llugwy', 'Mid and North Wales', [53.0880, -3.7950], ['River Llugwy', 'Afon Lliw'], flowsInto: 'conwy'),
            self::tributary('Afon Lledr', 'conwy', ['River Lledr']),

            // Dee
            self::main('River Dee', 'Dee', [53.2200, -3.0700], ['Afon Dyfrdwy', 'Dee Estuary', 'River Dee Estuary'], alsoRunsThrough: ['north-west' => ['chester-meadows']]),
            self::main('Afon Tryweryn', 'Dee', [52.9100, -3.6000], ['River Tryweryn'], flowsInto: 'dee'),
            self::tributary('Afon Alwen', 'dee', ['River Alwen']),
            self::tributary('River Ceiriog', 'dee', ['Afon Ceiriog']),
            self::tributary('Afon Eitha', 'dee', ['River Eitha'], slug: 'eitha'),
            self::tributary('Pulford Brook', 'dee'),
        ];
    }

    /**
     * @param  array{0: float, 1: float}  $mouth  Approximate [lat, lng] of the mouth
     * @param  array<int, string>  $extraAliases
     * @param  string|null  $flowsInto  Slug of the main river this one joins, if any
     * @param  string|null  $slug  For a river whose name another in the region already uses (the Galloway Dee)
     * @param  array<string, array<int, string>>  $alsoRunsThrough  Other regions it runs through, whose listings link to it, by slug, each with the slugs of its spots there (the Thames's in London)
     * @param  string|null  $osName  OS Open Rivers' name, where the site's says which of two same-named rivers it is (the Bristol Avon is OS's River Avon); also an alias
     * @return array{name: string, os_name: string|null, slug: string, catchment: string, aliases: array<int, string>, mouth: array{0: float, 1: float}, flows_into: string|null, also_runs_through: array<string, array<int, string>>|null}
     */
    private static function main(string $name, string $catchment, array $mouth, array $extraAliases = [], ?string $flowsInto = null, ?string $slug = null, array $alsoRunsThrough = [], ?string $osName = null): array
    {
        return ['name' => $name, 'os_name' => $osName, 'slug' => $slug ?? self::slug($name), 'catchment' => $catchment, 'aliases' => [$name, ...($osName === null ? [] : [$osName]), ...$extraAliases], 'mouth' => $mouth, 'flows_into' => $flowsInto, 'also_runs_through' => $alsoRunsThrough ?: null];
    }

    /**
     * The catchment is filled in from the parent when seeding.
     *
     * @param  array<int, string>  $extraAliases
     * @param  array{0: float, 1: float}|null  $mouth  Roughly where it joins its parent, for one sharing its name with another tributary of the same parent (Bristol's and Somerset's Fromes)
     * @return array{name: string, os_name: null, slug: string, catchment: string, aliases: array<int, string>, parent: string, mouth: array{0: float, 1: float}|null}
     */
    private static function tributary(string $name, string $parent, array $extraAliases = [], ?string $slug = null, ?array $mouth = null): array
    {
        return ['name' => $name, 'os_name' => null, 'slug' => $slug ?? self::slug($name), 'catchment' => '', 'aliases' => [$name, ...$extraAliases], 'parent' => $parent, 'mouth' => $mouth];
    }

    private static function slug(string $name): string
    {
        return Str::slug((string) preg_replace('/^(River|Afon) /', '', $name));
    }

    public function run(): void
    {
        foreach (self::rivers() as $regionSlug => $rivers) {
            $this->seedRegion(Region::query()->where('slug', $regionSlug)->firstOrFail(), $rivers);
        }
    }

    /**
     * @param  array<int, array{name: string, os_name?: string|null, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null, also_runs_through?: array<string, array<int, string>>|null}>  $rivers
     */
    private function seedRegion(Region $region, array $rivers): void
    {
        foreach ($rivers as $river) {
            $parentSlug = $river['parent'] ?? null;
            $mouth = $river['mouth'] ?? [null, null];
            unset($river['parent'], $river['mouth'], $river['flows_into']);

            $parent = $parentSlug === null ? null : $region->rivers()->where('slug', $parentSlug)->firstOrFail();

            // Rivers seeded before regions existed have no region yet, so claim those by slug rather than duplicating them.
            $existing = River::query()
                ->where('slug', $river['slug'])
                ->where(fn ($query) => $query->whereNull('region_id')->orWhere('region_id', $region->id))
                ->first();

            ($existing ?? new River())->fill([
                ...$river,
                'region_id' => $region->id,
                'catchment' => $river['catchment'] ?: $parent?->catchment,
                'parent_river_id' => $parent?->id,
                'mouth_latitude' => $mouth[0],
                'mouth_longitude' => $mouth[1],
            ])->save();
        }

        // Rivers a main river flows into can be listed after it (the Ouse comes after the Ure), so link them once all exist.
        foreach ($rivers as $river) {
            $flowsInto = isset($river['flows_into']) ? $region->rivers()->where('slug', $river['flows_into'])->firstOrFail() : null;

            $region->rivers()->where('slug', $river['slug'])->update(['flows_into_river_id' => $flowsInto?->id]);
        }
    }
}
