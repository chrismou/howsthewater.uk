<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('weather_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('cell_key')->unique();
            $table->decimal('latitude', 8, 5);
            $table->decimal('longitude', 8, 5);
            $table->json('data');
            $table->timestamp('forecast_at');
            $table->timestamp('fetched_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_forecasts');
    }
};
