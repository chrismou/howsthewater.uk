<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        foreach (['gauge_stations', 'rainfall_stations'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('network', 30)->default('environment_agency')->after('external_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['gauge_stations', 'rainfall_stations'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('network');
            });
        }
    }
};
