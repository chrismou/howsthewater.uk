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
        Schema::create('overflow_monitor_places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overflow_monitor_id')->constrained()->cascadeOnDelete();
            $table->morphs('place');
            // Along the river for a spot's upstream overflows, in a straight line for a beach's or lake's nearby ones.
            $table->unsignedInteger('distance_metres');

            $table->unique(['place_type', 'place_id', 'overflow_monitor_id'], 'overflow_monitor_places_place_monitor_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overflow_monitor_places');
    }
};
