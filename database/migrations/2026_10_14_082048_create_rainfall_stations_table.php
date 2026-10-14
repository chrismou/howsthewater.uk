<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rainfall_stations', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique()->comment('Environment Agency station reference');
            $table->string('label');
            $table->string('grid_reference')->nullable();
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->string('measure_id')->nullable()->comment('EA measure notation for the 15 minute rainfall total');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rainfall_stations');
    }
};
