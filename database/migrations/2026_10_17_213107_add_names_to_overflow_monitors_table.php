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
        Schema::table('overflow_monitors', function (Blueprint $table) {
            // The feed's own name (Scottish Water, Welsh Water); Stream feeds give none.
            $table->string('name')->nullable()->after('external_id');
            // The site name from the overflow's latest annual return, for monitors whose feed gives none.
            $table->string('return_name')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('overflow_monitors', function (Blueprint $table) {
            $table->dropColumn(['name', 'return_name']);
        });
    }
};
