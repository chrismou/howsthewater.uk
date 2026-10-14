<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionResource;
use App\Models\Region;
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
}
