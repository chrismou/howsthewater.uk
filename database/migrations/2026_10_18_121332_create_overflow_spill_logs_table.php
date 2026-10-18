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
        Schema::create('overflow_spill_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->string('key', 500)->unique();
            $table->string('name');
            $table->string('company')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('fingerprint')->nullable();
            $table->timestamp('covers_from')->nullable();
            $table->timestamp('covers_until')->nullable();
            $table->unsignedInteger('spills')->default(0);
            $table->unsignedInteger('unmatched')->default(0);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('company');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overflow_spill_logs');
    }
};
