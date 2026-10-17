<?php

namespace App\Http\Controllers;

use App\Models\OverflowMonitor;
use App\Services\Conditions\OverflowPageService;
use App\Services\Conditions\RegionOverflows;
use Illuminate\View\View;

/**
 * One storm overflow's page: its status, our record of its spills, the company's annual returns and the places it
 * affects. Only overflows some region's pages show have one.
 */
class OverflowController extends Controller
{
    public function show(OverflowMonitor $monitor, RegionOverflows $overflows, OverflowPageService $pages): View
    {
        $region = $overflows->regionFor($monitor);
        $spills = $pages->spillLog($monitor);

        abort_if($region === null || $spills === null, 404);

        return view('overflows.show', ['page' => $pages->for($monitor, $region), 'spills' => $spills]);
    }
}
