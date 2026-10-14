<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('gauge_stations', function (Blueprint $table) {
            $table->string('unit', 10)->nullable()->after('measure_id');
        });

        // The EA measure id ends with its unit, e.g. ...-15_min-m or ...-15_min-mAOD.
        DB::table('gauge_stations')->whereNotNull('measure_id')->where('measure_id', 'like', '%-mAOD')->update(['unit' => 'mAOD']);
        DB::table('gauge_stations')->whereNotNull('measure_id')->whereNull('unit')->update(['unit' => 'm']);
    }

    public function down(): void
    {
        Schema::table('gauge_stations', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
