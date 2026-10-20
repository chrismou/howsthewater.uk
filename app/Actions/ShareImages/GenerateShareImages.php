<?php

namespace App\Actions\ShareImages;

use App\Data\ShareCard;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\OverflowAnnualReturn;
use App\Models\OverflowMonitor;
use App\Models\OverflowMonitorPlace;
use App\Models\Region;
use App\Models\River;
use App\Models\ShareImage;
use App\Models\Spot;
use App\Services\ShareImages\ShareCardRenderer;
use App\Services\Conditions\RegionOverflows;
use App\Services\ShareImages\ShareCards;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Draws the share card for every spot, river, beach, lake and overflow page whose card has changed. A card is only
 * drawn when what it shows has changed (its fingerprint), so most runs draw nothing; a change to how cards are drawn
 * changes every one. The work splits into batches (batches()) that the queue draws in the background
 * (App\Jobs\QueueShareCards), or handle() draws in one go. Each file name carries the fingerprint, so a changed card gets
 * a new address; the old file stays for a day for pages and crawlers that still point at it, then goes, as do the
 * cards of places that no longer have a page.
 */
class GenerateShareImages
{
    public const DIRECTORY = 'share';

    /** How many places one queued job draws: a river comes with all its spots. */
    private const BATCH_SIZES = ['rivers' => 10, 'beaches' => 100, 'lakes' => 100, 'overflows' => 100];

    /** Superseded files are kept this long for cached pages and crawlers mid-fetch. */
    private const KEEP_OLD_FILES_HOURS = 24;

    public function __construct(private ShareCards $cards, private ShareCardRenderer $renderer, private RegionOverflows $overflows)
    {
    }

    /**
     * Draws every changed card in this process (locally and in tests; production queues the batches instead).
     *
     * @return array{drawn: int, unchanged: int, removed: int}
     */
    public function handle(bool $force = false): array
    {
        $counts = ['drawn' => 0, 'unchanged' => 0, 'removed' => $this->forgetPlacesWithoutPages()];

        foreach ($this->batches() as [$kind, $ids]) {
            $drawn = $this->draw($kind, $ids, $force);
            $counts['drawn'] += $drawn['drawn'];
            $counts['unchanged'] += $drawn['unchanged'];
        }

        $this->pruneOldFiles();

        return $counts;
    }

    /**
     * Every page with a card, split into one queued job's worth each: rivers (with their spots), beaches, lakes and
     * overflows, by id. Overflows go by river, so each job loads few rivers' lines.
     *
     * @return list<array{0: string, 1: list<int>}>
     */
    public function batches(): array
    {
        $ids = [
            'rivers' => River::query()->main()->orderBy('id')->pluck('id'),
            'beaches' => BathingWater::query()->beaches()->whereNotNull('region_id')->orderBy('id')->pluck('id'),
            'lakes' => Lake::query()->orderBy('id')->pluck('id'),
            'overflows' => $this->overflowsWithPages()->orderBy('river_id')->orderBy('id')->pluck('id'),
        ];

        $batches = [];
        foreach ($ids as $kind => $list) {
            foreach ($list->chunk(self::BATCH_SIZES[$kind]) as $chunk) {
                $batches[] = [$kind, $chunk->values()->all()];
            }
        }

        return $batches;
    }

    /**
     * Draws the changed cards of one batch.
     *
     * @param  list<int>  $ids
     * @return array{drawn: int, unchanged: int}
     */
    public function draw(string $kind, array $ids, bool $force = false): array
    {
        $counts = ['drawn' => 0, 'unchanged' => 0];
        $save = function (Spot|River|BathingWater|Lake|OverflowMonitor $place, string $folder, ShareCard $card) use ($force, &$counts) {
            $fingerprint = $card->fingerprint(ShareCardRenderer::VERSION);
            $image = $place->shareImage;

            if (! $force && $image?->fingerprint === $fingerprint && Storage::disk(ShareImage::DISK)->exists($image->path)) {
                $counts['unchanged']++;

                return;
            }

            $path = self::DIRECTORY."/{$folder}/{$place->getKey()}-{$fingerprint}.png";
            Storage::disk(ShareImage::DISK)->put($path, $this->renderer->render($card));
            $place->shareImage()->updateOrCreate([], ['path' => $path, 'fingerprint' => $fingerprint, 'alt' => $card->alt]);
            $counts['drawn']++;
        };

        match ($kind) {
            'rivers' => $this->drawRivers($ids, $save),
            'beaches' => BathingWater::query()->whereKey($ids)->whereNotNull('region_id')->with(['region', 'shareImage'])->get()
                ->each(fn (BathingWater $beach) => $save($beach, 'beaches', $this->cards->forBeach($beach, $beach->region))),
            'lakes' => Lake::query()->whereKey($ids)->with(['region', 'shareImage'])->get()
                ->each(fn (Lake $lake) => $save($lake, 'lakes', $this->cards->forLake($lake, $lake->region))),
            'overflows' => $this->drawOverflows($ids, $save),
            default => throw new InvalidArgumentException("No share cards are drawn for \"{$kind}\"."),
        };

        return $counts;
    }

    /**
     * Forgets the cards of places and overflows that no longer have a page.
     */
    public function forgetPlacesWithoutPages(): int
    {
        $mainRivers = River::query()->main()->select('id');
        $withPages = [
            (new River())->getMorphClass() => $mainRivers,
            (new Spot())->getMorphClass() => Spot::query()->published()->whereIn('river_id', $mainRivers)->select('id'),
            (new BathingWater())->getMorphClass() => BathingWater::query()->beaches()->whereNotNull('region_id')->select('id'),
            (new Lake())->getMorphClass() => Lake::query()->select('id'),
            (new OverflowMonitor())->getMorphClass() => $this->overflowsWithPages()->select('id'),
        ];

        $removed = 0;
        foreach ($withPages as $type => $ids) {
            $removed += ShareImage::query()->where('shareable_type', $type)->whereNotIn('shareable_id', $ids)->delete();
        }

        return $removed;
    }

    /**
     * Deletes card files no place points at any more, once they are a day old.
     */
    public function pruneOldFiles(): void
    {
        $disk = Storage::disk(ShareImage::DISK);
        $inUse = ShareImage::query()->pluck('path')->flip();
        $cutoff = now()->subHours(self::KEEP_OLD_FILES_HOURS)->getTimestamp();

        foreach ($disk->allFiles(self::DIRECTORY) as $path) {
            if (! $inUse->has($path) && $disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
            }
        }
    }

    /**
     * Rivers and their published spots, one river (with its long centreline) at a time.
     *
     * @param  list<int>  $ids
     */
    private function drawRivers(array $ids, Closure $save): void
    {
        foreach (River::query()->whereKey($ids)->main()->with(['region', 'shareImage', 'spots' => fn ($spots) => $spots->published()->with('shareImage')])->lazyById(5) as $river) {
            $save($river, 'rivers', $this->cards->forRiver($river, $river->region, $river->spots));
            foreach ($river->spots as $spot) {
                $save($spot, 'spots', $this->cards->forSpot($spot, $river, $river->region, $river->spots));
            }
        }
    }

    /**
     * Overflows by river, so each river's line, region and spots are loaded once.
     *
     * @param  list<int>  $ids
     */
    private function drawOverflows(array $ids, Closure $save): void
    {
        $river = null;
        $withReturns = OverflowAnnualReturn::query()->whereIn('overflow_monitor_id', $ids)->distinct()->pluck('overflow_monitor_id')->flip();

        foreach (OverflowMonitor::query()->whereKey($ids)->with('shareImage')->orderBy('river_id')->orderBy('id')->get() as $monitor) {
            if ($monitor->river_id === null) {
                $river = null;
            } elseif ($river?->id !== $monitor->river_id) {
                $river = River::query()->with(['region', 'spots' => fn ($spots) => $spots->published()])->find($monitor->river_id);
            }

            $region = $river?->region?->hasOverflowData() ? $river->region : $this->overflows->regionFor($monitor);

            if ($region !== null) {
                $save($monitor, 'overflows', $this->cards->forOverflow($monitor, $river, $region, $river === null ? collect() : $river->spots, $withReturns->has($monitor->id)));
            }
        }
    }

    /**
     * Overflows some region's pages show: on a river of a region with overflow data, or linked to a place.
     *
     * @return Builder<OverflowMonitor>
     */
    private function overflowsWithPages(): Builder
    {
        $regions = Region::query()->get()->filter(fn (Region $region) => $region->hasOverflowData());

        return OverflowMonitor::query()->where(fn ($query) => $query
            ->whereIn('river_id', $this->overflows->riverIdsQuery($regions))
            ->orWhereIn('id', OverflowMonitorPlace::query()->select('overflow_monitor_id')));
    }
}
