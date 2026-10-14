<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('gauge_stations', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique()->comment('Environment Agency station reference');
            $table->foreignId('river_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('river_name')->nullable();
            $table->string('town')->nullable();
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->unsignedInteger('river_metres')->nullable()->comment('Distance along river centreline from source');
            $table->string('measure_id')->nullable()->comment('EA measure notation for the level reading');
            $table->decimal('typical_range_low', 8, 3)->nullable();
            $table->decimal('typical_range_high', 8, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gauge_stations');
    }
};
