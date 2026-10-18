<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lines fetched by earlier readers that today's is better at: the Washburn,
 * Kennet and Wey stopped short of their upper reaches, and the rest doubled
 * back through same-named parallel channels. Clearing them makes the
 * deploy's rivers:centrelines step fetch them again, and rivers:positions
 * then repositions their monitors and spots. The Thames is never cleared;
 * see RiverSeeder.
 */
return new class () extends Migration {
    /**
     * @var array<string, array<int, string>>
     */
    private const RIVERS = [
        'yorkshire' => ['washburn', 'skell', 'worth'],
        'thames-valley' => ['kennet', 'wey', 'gade', 'ver'],
        'north-west' => ['lune'],
        'midlands' => ['sow', 'devon'],
        'london' => ['lea'],
        'east-of-england' => ['welland', 'chelmer'],
    ];

    public function up(): void
    {
        foreach (self::RIVERS as $region => $slugs) {
            DB::table('rivers')
                ->whereIn('slug', $slugs)
                ->whereIn('region_id', DB::table('regions')->where('slug', $region)->select('id'))
                ->update(['centreline' => null, 'length_metres' => null]);
        }
    }

    public function down(): void
    {
        // Nothing to restore; the next centrelines run fetches them again.
    }
};
