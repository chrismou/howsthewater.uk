<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Thames Colne's centreline stopped at Maple Cross, because a braided
 * reach around Rickmansworth broke the link chain and dropped everything
 * upstream. Clearing it makes the deploy's rivers:centrelines step fetch it
 * again with the fixed reader, and rivers:positions then repositions its
 * monitors and spots.
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::table('rivers')
            ->where('slug', 'colne')
            ->whereIn('region_id', DB::table('regions')->where('slug', 'thames-valley')->select('id'))
            ->update(['centreline' => null, 'length_metres' => null]);
    }

    public function down(): void
    {
        // Nothing to restore; the next centrelines run fetches it again.
    }
};
