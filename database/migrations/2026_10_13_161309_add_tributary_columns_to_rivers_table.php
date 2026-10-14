<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('rivers', function (Blueprint $table) {
            $table->foreignId('parent_river_id')->nullable()->after('id')->constrained('rivers')->nullOnDelete();
            $table->unsignedInteger('joins_at_metres')->nullable()->after('length_metres')
                ->comment('Distance along the parent river where this tributary joins it');
        });
    }

    public function down(): void
    {
        Schema::table('rivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_river_id');
            $table->dropColumn('joins_at_metres');
        });
    }
};
