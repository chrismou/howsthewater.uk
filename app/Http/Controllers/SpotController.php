<?php

namespace App\Http\Controllers;

use App\Models\Spot;
use App\Services\Conditions\SpotConditionsService;
use Illuminate\View\View;

class SpotController extends Controller
{
    public function show(Spot $spot, SpotConditionsService $conditions): View
    {
        abort_unless($spot->is_published, 404);

        return view('spots.show', ['conditions' => $conditions->for($spot)]);
    }
}
