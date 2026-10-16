<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEPA publishes Scottish samples only on each site's results page, which
 * is read one site at a time on a schedule of its own.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('bathing_waters', function (Blueprint $table) {
            $table->timestamp('samples_checked_at')->nullable()->after('history_fetched_at')->comment('Last read of the SEPA results page');
            $table->timestamp('samples_retry_at')->nullable()->after('samples_checked_at')->comment('After a failed read, when the SEPA results page may be read again');
        });
    }

    public function down(): void
    {
        Schema::table('bathing_waters', function (Blueprint $table) {
            $table->dropColumn(['samples_checked_at', 'samples_retry_at']);
        });
    }
};
