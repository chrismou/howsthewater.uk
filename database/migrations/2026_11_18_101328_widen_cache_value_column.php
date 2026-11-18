<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spots low on a big system (Goole) have over a thousand overflows upstream, and in a wet month their cached
 * conditions outgrow MEDIUMTEXT's 16 MB. MySQL then refuses the write, which failed the page and every
 * regions:refresh, so no region's summary was rebuilt. LONGTEXT holds up to 4 GB.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('cache', function (Blueprint $table) {
            $table->longText('value')->change();
        });
    }

    public function down(): void
    {
        Schema::table('cache', function (Blueprint $table) {
            $table->mediumText('value')->change();
        });
    }
};
