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
            // Other regions a main river runs through, by slug, each with the slugs of its spots there; their listings link to its one page.
            $table->json('also_runs_through')->nullable()->after('catchment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rivers', function (Blueprint $table) {
            $table->dropColumn('also_runs_through');
        });
    }
};
