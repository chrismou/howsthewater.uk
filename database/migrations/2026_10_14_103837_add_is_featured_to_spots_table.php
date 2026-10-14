<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('spots', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_published')->comment('Shown as a suggestion on the home page');
        });
    }

    public function down(): void
    {
        Schema::table('spots', function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }
};
