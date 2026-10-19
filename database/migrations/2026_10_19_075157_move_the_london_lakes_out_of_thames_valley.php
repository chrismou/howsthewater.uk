<?php

use App\Models\Lake;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hampstead Heath's ponds and the Serpentine were seeded under Thames Valley
 * before London had a region. London now has the ponds as Hampstead Heath,
 * so Thames Valley's copy goes, its bathing waters moving to London's, and
 * the Serpentine moves to London with its bathing water. The old addresses
 * redirect (routes/web.php). Overflow links follow on the deploy's
 * regions:refresh, which prunes those of a place without a page.
 */
return new class () extends Migration {
    public function up(): void
    {
        $london = DB::table('regions')->where('slug', 'london')->value('id');
        $thamesValley = DB::table('regions')->where('slug', 'thames-valley')->value('id');

        if ($london === null || $thamesValley === null) {
            return;
        }

        $ponds = DB::table('lakes')->where('region_id', $thamesValley)->where('slug', 'hampstead-heath-ponds')->value('id');
        $heath = DB::table('lakes')->where('region_id', $london)->where('slug', 'hampstead-heath')->value('id');

        if ($ponds !== null && $heath !== null) {
            DB::table('bathing_waters')->where('lake_id', $ponds)->update(['lake_id' => $heath, 'region_id' => $london]);
            DB::table('share_images')->where('shareable_type', Lake::class)->where('shareable_id', $ponds)->delete();
            DB::table('overflow_monitor_places')->where('place_type', Lake::class)->where('place_id', $ponds)->delete();
            DB::table('lakes')->where('id', $ponds)->delete();
        } elseif ($ponds !== null) {
            DB::table('lakes')->where('id', $ponds)->update(['region_id' => $london, 'name' => 'Hampstead Heath', 'slug' => 'hampstead-heath']);
            DB::table('bathing_waters')->where('lake_id', $ponds)->update(['region_id' => $london]);
        }

        $serpentine = DB::table('lakes')->where('region_id', $thamesValley)->where('slug', 'the-serpentine')->value('id');

        if ($serpentine !== null && ! DB::table('lakes')->where('region_id', $london)->where('slug', 'the-serpentine')->exists()) {
            DB::table('lakes')->where('id', $serpentine)->update(['region_id' => $london]);
            DB::table('bathing_waters')->where('lake_id', $serpentine)->update(['region_id' => $london]);
        }
    }

    public function down(): void
    {
        // The seeder no longer lists them under Thames Valley, so there is nothing to put back.
    }
};
