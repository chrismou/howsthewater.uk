<?php

namespace App\Http\Controllers;

use App\Models\Lake;
use App\Models\Region;
use App\Services\Conditions\NearbyPlaceFinder;
use App\Services\Conditions\WaterBodyConditionsService;
use Illuminate\View\View;

class LakeController extends Controller
{
    public function index(Region $region, WaterBodyConditionsService $conditions): View
    {
        $lakes = $region->lakes()->get();
        abort_if($lakes->isEmpty(), 404);

        return view('lakes.index', [
            'region' => $region,
            'lakes' => $conditions->forLakes($lakes),
        ]);
    }

    public function show(Region $region, Lake $lake, WaterBodyConditionsService $conditions, NearbyPlaceFinder $nearby): View
    {
        $lake->setRelation('region', $region);

        return view('lakes.show', ['region' => $region, 'conditions' => $conditions->forLake($lake), 'nearby' => $nearby->forLake($lake)]);
    }
}
