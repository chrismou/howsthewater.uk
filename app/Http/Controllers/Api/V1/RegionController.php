<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionResource;
use App\Http\Resources\RegionSummaryResource;
use App\Models\Region;
use App\Services\Conditions\RegionSummaries;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $regions = Region::query()
            ->with(['rivers' => fn ($query) => $query->main()])
            ->orderBy('name')
            ->get();

        return RegionResource::collection($regions);
    }

    public function show(Region $region, RegionSummaries $summaries): RegionSummaryResource
    {
        return new RegionSummaryResource($summaries->for($region));
    }
}
