<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpotConditionsResource;
use App\Http\Resources\SpotSummaryResource;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use App\Services\Conditions\SpotConditionsService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SpotController extends Controller
{
    public function index(Region $region): AnonymousResourceCollection
    {
        $spots = Spot::query()
            ->published()
            ->whereHas('river', fn ($query) => $query->where('region_id', $region->id))
            ->with('river.region')
            ->orderBy('river_id')
            ->orderBy('river_metres')
            ->get();

        return SpotSummaryResource::collection($spots);
    }

    public function show(Region $region, River $river, Spot $spot, SpotConditionsService $conditions): SpotConditionsResource
    {
        abort_unless($spot->is_published, 404);

        $spot->setRelation('river', $river->setRelation('region', $region));

        return new SpotConditionsResource($conditions->for($spot));
    }
}
