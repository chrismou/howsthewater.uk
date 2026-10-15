<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beaches and lakes link to their nearest rain gauge the way spots do, and
 * record when they were searched so the rainfall ingest catches up on new ones.
 */
return new class () extends Migration {
    public function up(): void
    {
        foreach (['lakes', 'bathing_waters'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('rainfall_station_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamp('rain_gauges_searched_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['lakes', 'bathing_waters'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('rainfall_station_id');
                $table->dropColumn('rain_gauges_searched_at');
            });
        }
    }
};
