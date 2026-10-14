<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records when each river was last searched for gauges and each spot for
 * rain gauges, so the scheduled ingests can catch up on anything newly
 * seeded without a manual run. Existing data counts as searched already.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('rivers', function (Blueprint $table) {
            $table->timestamp('gauges_discovered_at')->nullable()->after('joins_at_metres');
        });

        Schema::table('spots', function (Blueprint $table) {
            $table->timestamp('rain_gauges_searched_at')->nullable()->after('rainfall_station_id');
        });

        DB::table('rivers')->whereIn('id', DB::table('gauge_stations')->whereNotNull('river_id')->select('river_id'))->update(['gauges_discovered_at' => now()]);
        DB::table('spots')->whereNotNull('rainfall_station_id')->update(['rain_gauges_searched_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('spots', function (Blueprint $table) {
            $table->dropColumn('rain_gauges_searched_at');
        });

        Schema::table('rivers', function (Blueprint $table) {
            $table->dropColumn('gauges_discovered_at');
        });
    }
};
