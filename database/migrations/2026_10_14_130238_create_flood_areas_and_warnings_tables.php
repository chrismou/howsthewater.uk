<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Environment Agency flood alert and warning areas linked to the spots they
 * cover, plus the warnings currently in force for those areas. Only warnings
 * in force are kept; the next poll removes any that have been lifted.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('flood_areas', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('Environment Agency flood area notation (fwdCode)');
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('river_or_sea')->nullable();
            $table->timestamps();
        });

        Schema::create('flood_area_spot', function (Blueprint $table) {
            $table->foreignId('flood_area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spot_id')->constrained()->cascadeOnDelete();

            $table->primary(['flood_area_id', 'spot_id']);
        });

        Schema::create('flood_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flood_area_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('severity_level')->comment('1 severe flood warning, 2 flood warning, 3 flood alert');
            $table->text('message')->nullable();
            $table->timestamp('raised_at')->nullable();
            $table->timestamp('message_changed_at')->nullable();
            $table->timestamp('checked_at')->comment('Last poll that saw this warning in force');
            $table->timestamps();
        });

        Schema::table('spots', function (Blueprint $table) {
            $table->timestamp('flood_areas_searched_at')->nullable()->after('rain_gauges_searched_at');
        });
    }

    public function down(): void
    {
        Schema::table('spots', function (Blueprint $table) {
            $table->dropColumn('flood_areas_searched_at');
        });

        Schema::dropIfExists('flood_warnings');
        Schema::dropIfExists('flood_area_spot');
        Schema::dropIfExists('flood_areas');
    }
};
