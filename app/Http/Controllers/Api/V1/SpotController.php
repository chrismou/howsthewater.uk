<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpotConditionsResource;
use App\Http\Resources\SpotSummaryResource;
use App\Models\Spot;
use App\Services\Conditions\SpotConditionsService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SpotController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $spots = Spot::query()->published()->with('river')->orderBy('river_id')->orderBy('river_metres')->get();

        return SpotSummaryResource::collection($spots);
    }

    public function show(Spot $spot, SpotConditionsService $conditions): SpotConditionsResource
    {
        abort_unless($spot->is_published, 404);

        return new SpotConditionsResource($conditions->for($spot));
    }
}
