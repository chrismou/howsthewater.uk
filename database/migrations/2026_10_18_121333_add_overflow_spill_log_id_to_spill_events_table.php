<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('spill_events', function (Blueprint $table) {
            // Null for spills from the live feeds; set for spills imported from a company's published log.
            $table->foreignId('overflow_spill_log_id')->nullable()->after('overflow_monitor_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spill_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('overflow_spill_log_id');
        });
    }
};
