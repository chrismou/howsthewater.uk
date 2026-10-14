<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Beach URLs are /{region}/beaches/{slug}, so a slug must be unique within
 * its region or the second site could never be reached. Any duplicates
 * already stored get their sampling point appended before the index goes on.
 */
return new class () extends Migration {
    public function up(): void
    {
        $duplicates = DB::table('bathing_waters')
            ->select('region_id', 'slug')
            ->groupBy('region_id', 'slug')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('bathing_waters')
                ->where('region_id', $duplicate->region_id)
                ->where('slug', $duplicate->slug)
                ->orderBy('id')
                ->offset(1)
                ->limit(1000)
                ->get(['id', 'slug', 'sampling_point'])
                ->each(fn ($site) => DB::table('bathing_waters')->where('id', $site->id)->update(['slug' => "{$site->slug}-{$site->sampling_point}"]));
        }

        Schema::table('bathing_waters', function (Blueprint $table) {
            $table->unique(['region_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('bathing_waters', function (Blueprint $table) {
            $table->dropUnique(['region_id', 'slug']);
        });
    }
};
