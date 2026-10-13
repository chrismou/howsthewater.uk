<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('catchment')->nullable();
            $table->json('aliases')->comment('Receiving watercourse names that map to this river');
            $table->json('centreline')->nullable()->comment('Ordered [lat, lng] pairs from source to mouth');
            $table->unsignedInteger('length_metres')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rivers');
    }
};
