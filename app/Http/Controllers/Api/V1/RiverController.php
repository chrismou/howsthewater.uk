<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RiverResource;
use App\Models\River;
use App\Services\Conditions\RiverOverviewService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RiverController extends Controller
{
    public function index(RiverOverviewService $overview): AnonymousResourceCollection
    {
        return RiverResource::collection($overview->all());
    }

    public function show(River $river, RiverOverviewService $overview): RiverResource
    {
        abort_if($river->isTributary(), 404);

        return new RiverResource($overview->for($river));
    }
}
