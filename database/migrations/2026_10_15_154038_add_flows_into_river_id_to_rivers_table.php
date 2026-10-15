<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * A main river that joins another main river (the Ure into the Ouse)
     * keeps its own pages, unlike a tributary, but its overflows still
     * count upstream of the river it joins.
     */
    public function up(): void
    {
        Schema::table('rivers', function (Blueprint $table) {
            $table->foreignId('flows_into_river_id')->nullable()->after('parent_river_id')->constrained('rivers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('flows_into_river_id');
        });
    }
};
