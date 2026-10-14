<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->json('overflow_feeds')->nullable()->after('slug')->comment('Keys of services.stream.feeds that cover this region');
        });
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn('overflow_feeds');
        });
    }
};
