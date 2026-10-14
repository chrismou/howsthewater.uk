<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('rivers', function (Blueprint $table) {
            $table->decimal('mouth_latitude', 9, 6)->nullable()->after('aliases')
                ->comment('Approximate mouth, used to pick between same-named rivers in OS Open Rivers');
            $table->decimal('mouth_longitude', 9, 6)->nullable()->after('mouth_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('rivers', function (Blueprint $table) {
            $table->dropColumn(['mouth_latitude', 'mouth_longitude']);
        });
    }
};
