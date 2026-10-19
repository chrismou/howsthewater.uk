<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Environment Agency's own gauge pages (check-for-flooding.service.gov.uk/station/...) are addressed by the
 * station's River Levels on the Internet (RLOI) id, not its reference, so gauges keep it to link there. The weekly
 * station discovery fills it in for gauges already held.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('gauge_stations', function (Blueprint $table) {
            $table->string('rloi_id', 16)->nullable()->after('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('gauge_stations', function (Blueprint $table) {
            $table->dropColumn('rloi_id');
        });
    }
};
