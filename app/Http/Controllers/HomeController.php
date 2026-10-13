<?php

namespace App\Http\Controllers;

use App\Services\Conditions\RiverOverviewService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(RiverOverviewService $overview): View
    {
        return view('home', ['rivers' => $overview->all()]);
    }
}
