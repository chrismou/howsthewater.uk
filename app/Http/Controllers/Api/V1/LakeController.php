<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LakeConditionsResource;
use App\Models\Lake;
use App\Models\Region;
use App\Services\Conditions\WaterBodyConditionsService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LakeController extends Controller
{
    public function index(Region $region, WaterBodyConditionsService $conditions): AnonymousResourceCollection
    {
        return LakeConditionsResource::collection($conditions->forLakes($region->lakes()->get()));
    }

    public function show(Region $region, Lake $lake, WaterBodyConditionsService $conditions): LakeConditionsResource
    {
        return new LakeConditionsResource($conditions->forLake($lake));
    }
}
