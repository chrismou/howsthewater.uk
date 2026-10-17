<?php

namespace App\Http\Controllers;

use App\Models\BathingWater;
use App\Models\Region;
use App\Services\Conditions\NearbyPlaceFinder;
use App\Services\Conditions\WaterBodyConditionsService;
use App\Services\Weather\PlaceWeather;
use Illuminate\View\View;

class BeachController extends Controller
{
    public function index(Region $region, WaterBodyConditionsService $conditions): View
    {
        $beaches = $region->beaches()->get();
        abort_if($beaches->isEmpty(), 404);

        return view('beaches.index', [
            'region' => $region,
            'beaches' => $conditions->forBeaches($beaches),
        ]);
    }

    public function show(Region $region, BathingWater $beach, WaterBodyConditionsService $conditions, NearbyPlaceFinder $nearby, PlaceWeather $weather): View
    {
        return view('beaches.show', [
            'region' => $region,
            'conditions' => $conditions->forBeach($beach),
            'nearby' => $nearby->forBeach($beach->setRelation('region', $region)),
            'weather' => $weather->forecast($beach->latitude, $beach->longitude),
            'sun' => $weather->sunTimes($beach->latitude, $beach->longitude),
        ]);
    }
}
