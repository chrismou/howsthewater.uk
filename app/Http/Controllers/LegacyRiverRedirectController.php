<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Rivers and spots moved from /{region}/{river}/{spot} to
 * /{region}/rivers/{river}/{spot}. Their old addresses, and their feeds',
 * redirect permanently, so search results and feed subscriptions follow.
 * A river another region also lists (the Thames on London's pages) has its
 * page under its own region, so guessed addresses under the listing region
 * redirect there too.
 */
class LegacyRiverRedirectController extends Controller
{
    public function river(Region $region, River $river): RedirectResponse
    {
        return redirect()->route('rivers.show', [$region, $river], 301);
    }

    public function riverFeed(Region $region, River $river): RedirectResponse
    {
        return redirect()->route('rivers.feed', [$region, $river], 301);
    }

    public function spot(Region $region, River $river, Spot $spot): RedirectResponse
    {
        return redirect()->route('spots.show', [$region, $river, $spot], 301);
    }

    public function spotFeed(Region $region, River $river, Spot $spot): RedirectResponse
    {
        return redirect()->route('spots.feed', [$region, $river, $spot], 301);
    }

    /**
     * Called when a river or spot is not found under the region in the
     * address: redirects to the river's own region if this one lists it as
     * running through (/london/rivers/thames), and otherwise 404s.
     */
    public function riverElsewhere(Request $request): RedirectResponse
    {
        $region = $request->route('region');
        $riverSlug = $request->route('river');
        $spotSlug = $request->route('spot');

        // Only a region that was found has a slug to look up; the river and spot are still the address's words.
        abort_unless($region instanceof Region && is_string($riverSlug), 404);

        $river = River::query()
            ->main()
            ->withoutCentreline()
            ->where('slug', $riverSlug)
            ->whereJsonContainsKey('also_runs_through->'.$region->slug)
            ->with('region')
            ->first();

        abort_if($river?->region === null, 404);

        if ($spotSlug === null) {
            return redirect()->route('rivers.show', [$river->region, $river], 301);
        }

        $spot = is_string($spotSlug) ? $river->spots()->where('slug', $spotSlug)->first() : null;

        abort_if($spot === null, 404);

        return redirect()->route('spots.show', [$river->region, $river, $spot], 301);
    }
}
