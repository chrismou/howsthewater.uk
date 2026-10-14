<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('gauge_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gauge_station_id')->constrained()->cascadeOnDelete();
            $table->timestamp('measured_at');
            $table->decimal('value', 8, 3);
            $table->timestamps();

            $table->unique(['gauge_station_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gauge_readings');
    }
};
