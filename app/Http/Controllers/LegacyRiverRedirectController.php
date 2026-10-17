<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use Illuminate\Http\RedirectResponse;

/**
 * Rivers and spots moved from /{region}/{river}/{spot} to
 * /{region}/rivers/{river}/{spot}. Their old addresses, and their feeds',
 * redirect permanently, so search results and feed subscriptions follow.
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
}
