<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('bathing_waters', function (Blueprint $table) {
            $table->string('agency', 30)->default('environment_agency')->after('eubwid');
            $table->string('profile_url')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('bathing_waters', function (Blueprint $table) {
            $table->dropColumn(['agency', 'profile_url']);
        });
    }
};
