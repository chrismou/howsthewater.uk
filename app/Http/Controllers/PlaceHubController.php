<?php

namespace App\Http\Controllers;

use App\Data\RegionSummary;
use App\Models\Region;
use App\Services\Conditions\RegionSummaries;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The Rivers, Beaches and Lakes pages: every region with that kind of
 * place, as cards. They are where the header's menus link to, so the menus
 * still lead somewhere without JavaScript.
 */
class PlaceHubController extends Controller
{
    public function rivers(RegionSummaries $summaries): View
    {
        return $this->hub('rivers', $this->regions($summaries)->filter(fn (RegionSummary $summary) => $summary->rivers->isNotEmpty()));
    }

    public function beaches(RegionSummaries $summaries): View
    {
        return $this->hub('beaches', $this->regions($summaries)->filter(fn (RegionSummary $summary) => $summary->beaches->isNotEmpty()));
    }

    public function lakes(RegionSummaries $summaries): View
    {
        return $this->hub('lakes', $this->regions($summaries)->filter(fn (RegionSummary $summary) => $summary->lakes->isNotEmpty()));
    }

    /**
     * @return Collection<int, RegionSummary>
     */
    private function regions(RegionSummaries $summaries): Collection
    {
        return Region::query()->orderBy('name')->get()->map(fn (Region $region) => $summaries->for($region));
    }

    /**
     * @param  Collection<int, RegionSummary>  $regions
     */
    private function hub(string $type, Collection $regions): View
    {
        return view('hubs.show', ['type' => $type, 'regions' => $regions->values()]);
    }
}
