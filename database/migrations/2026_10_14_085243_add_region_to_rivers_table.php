<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rivers belong to a region and slugs are only unique within it, since
 * names like Calder, Derwent and Ouse repeat across Britain. Spot slugs
 * likewise only need to be unique on their river.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('rivers', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['slug']);
            $table->unique(['region_id', 'slug']);
        });

        Schema::table('spots', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['river_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('spots', function (Blueprint $table) {
            $table->dropUnique(['river_id', 'slug']);
            $table->unique(['slug']);
        });

        Schema::table('rivers', function (Blueprint $table) {
            $table->dropUnique(['region_id', 'slug']);
            $table->unique(['slug']);
            $table->dropConstrainedForeignId('region_id');
        });
    }
};
