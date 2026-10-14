<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\River;
use App\Services\Conditions\RiverOverviewService;
use Illuminate\View\View;

class RiverController extends Controller
{
    public function show(Region $region, River $river, RiverOverviewService $overview): View
    {
        abort_if($river->isTributary(), 404);

        return view('rivers.show', ['overview' => $overview->for($river->setRelation('region', $region))]);
    }
}
