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
            $table->decimal('joins_latitude', 9, 6)->nullable()->after('mouth_longitude')
                ->comment('Where the river really joins its parent, for a line that stops short (the Irwell at the Ship Canal)');
            $table->decimal('joins_longitude', 9, 6)->nullable()->after('joins_latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rivers', function (Blueprint $table) {
            $table->dropColumn(['joins_latitude', 'joins_longitude']);
        });
    }
};
