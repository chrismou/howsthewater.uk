<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overflow_monitors', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique()->comment('Water company monitor id, e.g. YWS00191');
            $table->foreignId('river_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company');
            $table->string('receiving_watercourse')->nullable();
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->unsignedInteger('river_metres')->nullable()->comment('Distance along river centreline from source');
            $table->tinyInteger('status')->default(0);
            $table->timestamp('status_started_at')->nullable();
            $table->timestamp('latest_event_started_at')->nullable();
            $table->timestamp('latest_event_ended_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();

            $table->index(['river_id', 'river_metres']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overflow_monitors');
    }
};
