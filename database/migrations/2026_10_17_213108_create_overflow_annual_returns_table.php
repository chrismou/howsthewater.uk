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
        Schema::create('overflow_annual_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->foreignId('overflow_monitor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('uid', 32)->nullable();
            $table->string('company');
            $table->string('site_name')->nullable();
            $table->string('permit_reference')->nullable();
            $table->unsignedInteger('counted_spills')->nullable();
            $table->decimal('total_hours', 8, 2)->nullable();
            $table->decimal('monitor_uptime_percent', 5, 2)->nullable();
            $table->string('receiving_water')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->timestamps();

            $table->index('year');
            $table->index(['overflow_monitor_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overflow_annual_returns');
    }
};
