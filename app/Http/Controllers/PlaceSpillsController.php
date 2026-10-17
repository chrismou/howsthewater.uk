<?php

namespace App\Http\Controllers;

use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use App\Services\Conditions\PlaceSpillsService;
use Illuminate\View\View;

/**
 * The full spill history of the overflows upstream of a spot, or near a beach or lake. Places in a region whose
 * overflow feed we do not read have none.
 */
class PlaceSpillsController extends Controller
{
    public function spot(Region $region, River $river, Spot $spot, PlaceSpillsService $spills): View
    {
        abort_unless($spot->is_published, 404);
        abort_if($river->isTributary(), 404);

        $spot->setRelation('river', $river->setRelation('region', $region));

        return $this->page($spot, $region, $spills);
    }

    public function beach(Region $region, BathingWater $beach, PlaceSpillsService $spills): View
    {
        return $this->page($beach, $region, $spills);
    }

    public function lake(Region $region, Lake $lake, PlaceSpillsService $spills): View
    {
        return $this->page($lake, $region, $spills);
    }

    private function page(Spot|BathingWater|Lake $place, Region $region, PlaceSpillsService $spills): View
    {
        $history = $spills->for($place, $region);
        $log = $history === null ? null : $spills->spillLog($place, $history->url());

        abort_if($history === null || $log === null, 404);

        return view('spills.place', ['history' => $history, 'spills' => $log, 'shareImage' => $place->shareImage]);
    }
}
