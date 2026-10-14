<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RiverResource;
use App\Models\Region;
use App\Models\River;
use App\Services\Conditions\RiverOverviewService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RiverController extends Controller
{
    public function index(Region $region, RiverOverviewService $overview): AnonymousResourceCollection
    {
        return RiverResource::collection($overview->forRegion($region));
    }

    public function show(Region $region, River $river, RiverOverviewService $overview): RiverResource
    {
        abort_if($river->isTributary(), 404);

        return new RiverResource($overview->for($river->setRelation('region', $region)));
    }
}
