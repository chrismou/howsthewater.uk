<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('flood_areas', function (Blueprint $table) {
            $table->string('agency', 30)->default('environment_agency')->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('flood_areas', function (Blueprint $table) {
            $table->dropColumn('agency');
        });
    }
};
