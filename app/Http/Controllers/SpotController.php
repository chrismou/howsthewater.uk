<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use App\Services\Conditions\SpotConditionsService;
use Illuminate\View\View;

class SpotController extends Controller
{
    public function show(Region $region, River $river, Spot $spot, SpotConditionsService $conditions): View
    {
        abort_unless($spot->is_published, 404);

        $spot->setRelation('river', $river->setRelation('region', $region));

        return view('spots.show', ['conditions' => $conditions->for($spot), 'river' => $river]);
    }
}
