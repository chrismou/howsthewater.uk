<?php

namespace App\Http\Controllers;

use App\Models\River;
use App\Models\Spot;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Finds spots and rivers by name, so a town name gets someone to the right
 * page without knowing which river runs through it. There are only a few
 * hundred names, so matching happens in PHP: it is case-insensitive on
 * every database and treats wildcard characters literally.
 */
class SearchController extends Controller
{
    private const MAX_RESULTS = 25;

    private const MAX_QUERY_LENGTH = 100;

    public function __invoke(Request $request): View
    {
        $query = Str::limit(trim((string) $request->query('q', '')), self::MAX_QUERY_LENGTH, '');
        $matches = fn (string $name) => $query !== '' && Str::contains($name, $query, ignoreCase: true);

        $spots = Spot::query()
            ->published()
            ->onMainRiver()
            ->with('river.region')
            ->orderBy('name')
            ->get()
            ->filter(fn (Spot $spot) => $matches($spot->name))
            ->take(self::MAX_RESULTS);

        $rivers = River::query()
            ->main()
            ->withoutCentreline()
            ->with('region')
            ->orderBy('name')
            ->get()
            ->filter(fn (River $river) => $matches($river->name))
            ->take(self::MAX_RESULTS);

        return view('search', ['query' => $query, 'spots' => $spots, 'rivers' => $rivers]);
    }
}
