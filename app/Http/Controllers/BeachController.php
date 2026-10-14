<?php

namespace App\Http\Controllers;

use App\Models\BathingWater;
use App\Models\Region;
use App\Services\Conditions\WaterBodyConditionsService;
use Illuminate\View\View;

class BeachController extends Controller
{
    public function index(Region $region, WaterBodyConditionsService $conditions): View
    {
        $beaches = $region->beaches()->get();
        abort_if($beaches->isEmpty(), 404);

        return view('beaches.index', [
            'region' => $region,
            'beaches' => $beaches->map(fn (BathingWater $beach) => $conditions->forBeach($beach)),
        ]);
    }

    public function show(Region $region, BathingWater $beach, WaterBodyConditionsService $conditions): View
    {
        return view('beaches.show', ['region' => $region, 'conditions' => $conditions->forBeach($beach)]);
    }
}
