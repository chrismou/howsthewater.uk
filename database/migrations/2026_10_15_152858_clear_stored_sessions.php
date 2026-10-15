<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Pages no longer start sessions, so nothing would ever garbage collect the
     * visitor IP addresses and user agents already stored. Clear them out.
     */
    public function up(): void
    {
        DB::table('sessions')->delete();
    }

    /**
     * The deleted sessions cannot be restored.
     */
    public function down(): void
    {
        //
    }
};
