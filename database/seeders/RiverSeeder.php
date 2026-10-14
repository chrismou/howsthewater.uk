<?php

namespace Database\Seeders;

use App\Models\River;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RiverSeeder extends Seeder
{
    /**
     * Main rivers first, then tributaries (which reference their parent by
     * slug). Tributaries only contribute monitors; they get no pages. Names
     * must match OS Open Rivers; aliases are the receiving watercourse names
     * the overflow feed and the Environment Agency use. Main rivers carry an
     * approximate mouth so same-named rivers elsewhere in Britain are ignored.
     *
     * @return array<int, array{name: string, slug: string, catchment: string, aliases: array<int, string>, mouth?: array{0: float, 1: float}, parent?: string}>
     */
    public static function rivers(): array
    {
        return [
            // Wharfe
            self::main('River Wharfe', 'Yorkshire Ouse', [53.8415, -1.1295]),
            self::tributary('River Washburn', 'wharfe'),
            self::tributary('Kex Beck', 'wharfe'),
            self::tributary('Weeton Beck', 'wharfe'),
            self::tributary('Keswick Beck', 'wharfe'),
            self::tributary('Bardsey Beck', 'keswick-beck'),
            self::tributary('Collingham Beck', 'wharfe'),
            self::tributary('Cock Beck', 'wharfe'),

            // Aire
            self::main('River Aire', 'Yorkshire Ouse', [53.7217, -0.8950]),
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
            self::main('River Calder', 'Yorkshire Ouse', [53.7215, -1.3595]),
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
            self::main('River Don', 'Yorkshire Ouse', [53.674, -0.980]),
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
            self::main('River Nidd', 'Yorkshire Ouse', [54.0205, -1.2185]),
            self::tributary('Oak Beck', 'nidd'),
            self::tributary('Crimple Beck', 'nidd', ['River Crimple', 'Crimple Beck Or River Crimple']),

            // Ure
            self::main('River Ure', 'Yorkshire Ouse', [54.039, -1.276]),
            self::tributary('River Bain', 'ure'),
            self::tributary('River Cover', 'ure'),
            self::tributary('River Burn', 'ure'),
            self::tributary('River Skell', 'ure'),
            self::tributary('River Laver', 'skell'),
            self::tributary('Bishop Monkton Beck', 'ure'),

            // Swale
            self::main('River Swale', 'Yorkshire Ouse', [54.089, -1.323]),
            self::tributary('Skeeby Beck', 'swale'),
            self::tributary('Bedale Beck', 'swale'),
            self::tributary('River Wiske', 'swale'),
            self::tributary('Cod Beck', 'swale'),

            // Derwent
            self::main('River Derwent', 'Yorkshire Ouse', [53.749, -0.969]),
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
     * @param  array{0: float, 1: float}  $mouth  Approximate [lat, lng] of the mouth
     * @return array{name: string, slug: string, catchment: string, aliases: array<int, string>, mouth: array{0: float, 1: float}}
     */
    private static function main(string $name, string $catchment, array $mouth): array
    {
        return ['name' => $name, 'slug' => self::slug($name), 'catchment' => $catchment, 'aliases' => [$name], 'mouth' => $mouth];
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
        foreach (self::rivers() as $river) {
            $parentSlug = $river['parent'] ?? null;
            $mouth = $river['mouth'] ?? [null, null];
            unset($river['parent'], $river['mouth']);

            $parent = $parentSlug === null ? null : River::query()->where('slug', $parentSlug)->firstOrFail();

            River::query()->updateOrCreate(['slug' => $river['slug']], [
                ...$river,
                'catchment' => $river['catchment'] ?: $parent?->catchment,
                'parent_river_id' => $parent?->id,
                'mouth_latitude' => $mouth[0],
                'mouth_longitude' => $mouth[1],
            ]);
        }
    }
}
