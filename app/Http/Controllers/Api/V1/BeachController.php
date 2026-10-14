<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeachConditionsResource;
use App\Models\BathingWater;
use App\Models\Region;
use App\Services\Conditions\WaterBodyConditionsService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BeachController extends Controller
{
    public function index(Region $region, WaterBodyConditionsService $conditions): AnonymousResourceCollection
    {
        return BeachConditionsResource::collection($region->beaches()->get()->map(fn (BathingWater $beach) => $conditions->forBeach($beach)));
    }

    public function show(Region $region, BathingWater $beach, WaterBodyConditionsService $conditions): BeachConditionsResource
    {
        return new BeachConditionsResource($conditions->forBeach($beach));
    }
}
