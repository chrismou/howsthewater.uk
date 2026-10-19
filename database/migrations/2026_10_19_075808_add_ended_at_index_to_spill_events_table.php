<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The spills pages list every spill running or ended in the last 24 hours. With the companies' published logs in
 * (millions of rows), finding those through each overflow's spills took seconds; an index on the end lets the
 * database read just the open and recent ones.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('spill_events', function (Blueprint $table) {
            $table->index('ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('spill_events', function (Blueprint $table) {
            $table->dropIndex(['ended_at']);
        });
    }
};
