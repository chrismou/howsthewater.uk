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
        Schema::table('rivers', function (Blueprint $table) {
            // The OS watercourse that carries the river's water on to the river it flows into, where OS ends the river
            // short of it (the Irwell, which becomes the Manchester Ship Canal at Pomona).
            $table->string('continues_along')->nullable()->after('os_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rivers', function (Blueprint $table) {
            $table->dropColumn('continues_along');
        });
    }
};
