<?php

namespace App\Http\Controllers;

use App\Models\River;
use App\Models\Spot;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Finds spots and rivers by name, so a town name gets someone to the right
 * page without knowing which river runs through it.
 */
class SearchController extends Controller
{
    private const MAX_RESULTS = 25;

    public function __invoke(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));

        $spots = $query === '' ? collect() : Spot::query()
            ->published()
            ->whereLike('name', "%{$query}%")
            ->with('river.region')
            ->orderBy('name')
            ->limit(self::MAX_RESULTS)
            ->get();

        $rivers = $query === '' ? collect() : River::query()
            ->main()
            ->whereLike('name', "%{$query}%")
            ->with('region')
            ->orderBy('name')
            ->limit(self::MAX_RESULTS)
            ->get();

        return view('search', ['query' => $query, 'spots' => $spots, 'rivers' => $rivers]);
    }
}
