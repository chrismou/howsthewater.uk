<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A history of flood alerts and warnings across England: one row per area
 * and severity, from when the Environment Agency raised it until it was
 * lifted or changed severity. flood_warnings only ever holds what is in
 * force now, and the EA keeps no history in its live feed.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('flood_areas', function (Blueprint $table) {
            $table->string('county')->nullable()->after('river_or_sea');
        });

        Schema::create('flood_warning_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flood_area_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('severity_level')->comment('1 severe flood warning, 2 flood warning, 3 flood alert');
            $table->timestamp('started_at')->comment('When the EA raised the area to this severity');
            $table->timestamp('ended_at')->nullable()->comment('When it was lifted or changed severity; null while in force');
            $table->timestamp('last_seen_at')->comment('Last poll that saw it in force');
            $table->text('message')->nullable()->comment('The latest message the EA published for it');
            $table->timestamp('message_changed_at')->nullable();
            $table->timestamps();

            $table->index(['flood_area_id', 'ended_at']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flood_warning_events');

        Schema::table('flood_areas', function (Blueprint $table) {
            $table->dropColumn('county');
        });
    }
};
