<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rainfall_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rainfall_station_id')->constrained()->cascadeOnDelete();
            $table->timestamp('measured_at')->comment('End of the 15 minute period the total covers');
            $table->decimal('millimetres', 6, 2);
            $table->timestamps();

            $table->unique(['rainfall_station_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rainfall_readings');
    }
};
