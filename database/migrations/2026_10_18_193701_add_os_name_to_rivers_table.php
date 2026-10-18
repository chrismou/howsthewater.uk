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
            // The name OS Open Rivers knows it by, where the site names it more plainly (the Bristol Avon is OS's River Avon).
            $table->string('os_name')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rivers', function (Blueprint $table) {
            $table->dropColumn('os_name');
        });
    }
};
