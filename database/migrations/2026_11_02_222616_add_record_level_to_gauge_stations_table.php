<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Each gauge's highest level on record, as its agency publishes it: the Environment Agency gives the reading and
     * its moment, SEPA its monthly maxima (so the month is known, not the day) and when its records begin.
     */
    public function up(): void
    {
        Schema::table('gauge_stations', function (Blueprint $table) {
            $table->decimal('record_level', 9, 3)->nullable()->after('typical_range_high');
            $table->dateTime('record_level_at')->nullable()->after('record_level');
            $table->boolean('record_level_known_to_month')->default(false)->after('record_level_at');
            $table->date('records_from')->nullable()->after('record_level_known_to_month');
        });
    }

    public function down(): void
    {
        Schema::table('gauge_stations', function (Blueprint $table) {
            $table->dropColumn(['record_level', 'record_level_at', 'record_level_known_to_month', 'records_from']);
        });
    }
};
