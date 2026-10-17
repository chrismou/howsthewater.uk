<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('share_images', function (Blueprint $table) {
            $table->id();
            $table->morphs('shareable');
            $table->string('path');
            $table->string('fingerprint', 32);
            $table->string('alt', 300);
            $table->timestamps();

            $table->unique(['shareable_type', 'shareable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_images');
    }
};
