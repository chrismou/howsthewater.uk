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
     * @return array<string, array<int, array{name: string, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null}>>
     */
    public static function rivers(): array
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
     * @return array<int, array{name: string, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null}>
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
     * that join above Teddington. Rivers joining below it (the Lee, Brent,
     * Wandle, Roding) only affect tidal water, so they are left out.
     *
     * @return array<int, array{name: string, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null}>
     */
    private static function thamesValley(): array
    {
        return [
            self::main('River Thames', 'Thames', [51.5055, -0.0880], ['River Isis', 'Seacourt Stream', 'Shill Brook']),
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
     * @return array<int, array{name: string, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null}>
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
     * @return array<int, array{name: string, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null}>
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
            self::main('River Tweed', 'Tweed', [55.7660, -2.0050]),
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
     * Severn Trent's rivers: the Peak District rivers and the Soar flowing
     * into the Trent, and the Teme and Warwickshire Avon into the Severn.
     * OS names the Severn and Teme in Welsh first; the reader matches their
     * English names as alternatives. Overflows on the Welsh upper Severn
     * belong to Hafren Dyfrdwy, which is not on Stream, so they do not
     * count yet.
     *
     * @return array<int, array{name: string, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null}>
     */
    private static function midlands(): array
    {
        return [
            // Trent
            self::main('River Trent', 'Trent', [53.7000, -0.6900], ['River Trent Via Beckvalley Cul']),
            self::tributary('River Tame', 'trent'),
            self::tributary('River Rea', 'tame'),
            self::tributary('River Cole', 'tame'),
            self::tributary('Ford Brook', 'tame'),
            self::tributary('River Anker', 'tame'),
            self::tributary('River Blythe', 'tame', ['River Blythe (Trib)']),
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
            self::tributary('River Wreake', 'soar', ['River Wreake (Eye) Trib']),
            self::tributary('River Sence', 'soar', ['River Sence (Soar)']),

            // Peak District rivers, all flowing into the Trent
            self::main('River Derwent', 'Peak District', [52.8700, -1.3700], flowsInto: 'trent'),
            self::tributary('River Noe', 'derwent'),
            self::tributary('River Amber', 'derwent'),
            self::tributary('River Ecclesbourne', 'derwent'),
            self::tributary('Markeaton Brook', 'derwent'),
            self::main('River Wye', 'Peak District', [53.1880, -1.6160], flowsInto: 'derwent'),
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
     * @param  array{0: float, 1: float}  $mouth  Approximate [lat, lng] of the mouth
     * @param  array<int, string>  $extraAliases
     * @param  string|null  $flowsInto  Slug of the main river this one joins, if any
     * @return array{name: string, slug: string, catchment: string, aliases: array<int, string>, mouth: array{0: float, 1: float}, flows_into: string|null}
     */
    private static function main(string $name, string $catchment, array $mouth, array $extraAliases = [], ?string $flowsInto = null): array
    {
        return ['name' => $name, 'slug' => self::slug($name), 'catchment' => $catchment, 'aliases' => [$name, ...$extraAliases], 'mouth' => $mouth, 'flows_into' => $flowsInto];
    }

    /**
     * The catchment is filled in from the parent when seeding.
     *
     * @param  array<int, string>  $extraAliases
     * @return array{name: string, slug: string, catchment: string, aliases: array<int, string>, parent: string}
     */
    private static function tributary(string $name, string $parent, array $extraAliases = [], ?string $slug = null): array
    {
        return ['name' => $name, 'slug' => $slug ?? self::slug($name), 'catchment' => '', 'aliases' => [$name, ...$extraAliases], 'parent' => $parent];
    }

    private static function slug(string $name): string
    {
        return Str::slug((string) preg_replace('/^River /', '', $name));
    }

    public function run(): void
    {
        foreach (self::rivers() as $regionSlug => $rivers) {
            $this->seedRegion(Region::query()->where('slug', $regionSlug)->firstOrFail(), $rivers);
        }
    }

    /**
     * @param  array<int, array{name: string, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string, flows_into?: string|null}>  $rivers
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
