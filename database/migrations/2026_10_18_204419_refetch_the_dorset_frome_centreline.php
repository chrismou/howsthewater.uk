<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Dorset Frome's centreline doubled back about 5 km through a parallel
 * channel near Woodsford, which the reader now cuts out. Clearing it makes
 * the deploy's rivers:centrelines step fetch it again, and rivers:positions
 * then repositions its monitors and spots.
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::table('rivers')
            ->where('slug', 'dorset-frome')
            ->whereIn('region_id', DB::table('regions')->where('slug', 'wessex')->select('id'))
            ->update(['centreline' => null, 'length_metres' => null]);
    }

    public function down(): void
    {
        // Nothing to restore; the next centrelines run fetches it again.
    }
};
