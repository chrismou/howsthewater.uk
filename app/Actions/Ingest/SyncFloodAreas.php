<?php

namespace App\Actions\Ingest;

use App\Data\FloodAreaData;
use App\Enums\Agency;
use App\Models\FloodArea;
use App\Models\River;
use App\Models\Spot;
use App\Services\EnvironmentAgency\FloodMonitoringClient;
use App\Services\NaturalResourcesWales\FloodWarningsClient;
use App\Services\Sepa\FloodWarningsClient as SepaFloodWarningsClient;
use Illuminate\Support\Collection;

/**
 * Links each spot to the Environment Agency flood areas that cover it, in and
 * near Wales to Natural Resources Wales's, and in Scotland to SEPA's. All match
 * against each area's outline, so a small radius finds the areas the spot sits
 * in or beside; the Environment Agency's and NRW's are then kept only where
 * they name the spot's river, so a warning for a beck that passes nearby is not
 * shown. SEPA's are kept as found, as it names its areas by place.
 */
class SyncFloodAreas
{
    /**
     * How close an area's outline must come to the spot. Spots sit on the
     * river centreline, which an outline can trace just inside its bank.
     */
    public const SEARCH_RADIUS_KM = 1;

    public function __construct(private FloodMonitoringClient $client, private FloodWarningsClient $nrw, private SepaFloodWarningsClient $sepa)
    {
    }

    /**
     * @return array{spots: int, areas: int}
     */
    public function handle(bool $onlyUnsearched = false): array
    {
        $spots = Spot::query()
            ->published()
            ->when($onlyUnsearched, fn ($query) => $query->whereNull('flood_areas_searched_at'))
            ->with('river')
            ->get();

        $linkedSpots = 0;
        $areaIds = [];

        foreach ($spots as $spot) {
            $areas = $this->areasNear($spot)
                ->filter(fn (FloodAreaData $area) => $this->namesRiver($area, $spot->river))
                ->map(fn (FloodAreaData $area) => FloodArea::query()->updateOrCreate(
                    ['code' => $area->code],
                    ['agency' => $area->agency, 'label' => $area->label, 'description' => $area->description, 'river_or_sea' => $area->riverOrSea],
                ));

            $spot->floodAreas()->sync($areas->pluck('id')->all());
            $spot->update(['flood_areas_searched_at' => now()]);

            if ($areas->isNotEmpty()) {
                $linkedSpots++;
            }

            array_push($areaIds, ...$areas->pluck('id')->all());
        }

        return ['spots' => $linkedSpots, 'areas' => count(array_unique($areaIds))];
    }

    /**
     * NRW's and SEPA's areas are only searched while their warnings are read,
     * since nothing would be shown for them without the warnings.
     *
     * @return Collection<int, FloodAreaData>
     */
    private function areasNear(Spot $spot): Collection
    {
        $areas = $this->client->floodAreasNear($spot->latitude, $spot->longitude, self::SEARCH_RADIUS_KM);

        if ($this->nrw->isEnabled() && $this->nrw->covers($spot->latitude, $spot->longitude)) {
            $areas = $areas->concat($this->nrw->floodAreasNear($spot->latitude, $spot->longitude, self::SEARCH_RADIUS_KM));
        }

        if ($this->sepa->isEnabled() && $this->sepa->covers($spot->latitude, $spot->longitude)) {
            $areas = $areas->concat($this->sepa->floodAreasNear($spot->latitude, $spot->longitude, self::SEARCH_RADIUS_KM));
        }

        return $areas;
    }

    /**
     * NRW names some rivers without "River" ("Rhondda") and leaves some
     * catchment-wide alert areas unnamed ("Lower Dee catchment"), so for its
     * areas the area's own name or description may name the river instead.
     * SEPA's alert areas are whole regions (Tayside) and its warning areas are
     * named by place, with the river in the scheme's name only sometimes (the
     * Tay at Perth sits in the "Almond" scheme, the Forth in "Stirling"), so a
     * name check would drop real warnings; its areas are small enough for the
     * search radius alone to place them.
     */
    private function namesRiver(FloodAreaData $area, River $river): bool
    {
        if ($area->agency === Agency::Sepa) {
            return true;
        }

        if ($area->agency !== Agency::NaturalResourcesWales) {
            return collect($area->watercourses())->contains(fn (string $name) => $river->matchesWatercourse($name));
        }

        if ($area->watercourses() !== []) {
            return collect($area->watercourses())->contains(fn (string $name) => $river->matchesBareName($name));
        }

        $text = $area->label.' '.$area->description;

        return collect($river->aliases ?? [])->contains(fn (string $alias) => preg_match('/\b'.preg_quote($alias, '/').'\b/iu', $text) === 1);
    }
}
