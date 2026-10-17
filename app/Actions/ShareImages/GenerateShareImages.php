<?php

namespace App\Actions\ShareImages;

use App\Data\ShareCard;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\River;
use App\Models\ShareImage;
use App\Models\Spot;
use App\Services\ShareImages\ShareCardRenderer;
use App\Services\ShareImages\ShareCards;
use Illuminate\Support\Facades\Storage;

/**
 * Draws the share card for every spot, river, beach and lake page, run on each deploy. A card is only drawn when what
 * it shows has changed (its fingerprint), so most runs draw nothing. Each file name carries the fingerprint, so a
 * changed card gets a new address; the old file stays for a day for pages and crawlers that still point at it, then
 * goes, as do the cards of places that no longer have a page.
 */
class GenerateShareImages
{
    public const DIRECTORY = 'share';

    /** Superseded files are kept this long for cached pages and crawlers mid-fetch. */
    private const KEEP_OLD_FILES_HOURS = 24;

    public function __construct(private ShareCards $cards, private ShareCardRenderer $renderer)
    {
    }

    /**
     * @return array{drawn: int, unchanged: int, removed: int}
     */
    public function handle(bool $force = false): array
    {
        $counts = ['drawn' => 0, 'unchanged' => 0, 'removed' => 0];
        $existing = ShareImage::query()->get()->keyBy(fn (ShareImage $image) => $image->shareable_type.':'.$image->shareable_id);
        $seen = [];

        $save = function (Spot|River|BathingWater|Lake $place, string $folder, ShareCard $card) use ($existing, $force, &$seen, &$counts) {
            $key = $place->getMorphClass().':'.$place->getKey();
            $seen[$key] = true;
            $fingerprint = $card->fingerprint(ShareCardRenderer::VERSION);
            $image = $existing->get($key);

            if (! $force && $image?->fingerprint === $fingerprint && Storage::disk(ShareImage::DISK)->exists($image->path)) {
                $counts['unchanged']++;

                return;
            }

            $path = self::DIRECTORY."/{$folder}/{$place->getKey()}-{$fingerprint}.png";
            Storage::disk(ShareImage::DISK)->put($path, $this->renderer->render($card));
            $place->shareImage()->updateOrCreate([], ['path' => $path, 'fingerprint' => $fingerprint, 'alt' => $card->alt]);
            $counts['drawn']++;
        };

        // One river (with its long centreline) at a time.
        foreach (River::query()->main()->with(['region', 'spots' => fn ($spots) => $spots->published()])->lazyById(20) as $river) {
            $save($river, 'rivers', $this->cards->forRiver($river, $river->region, $river->spots));
            foreach ($river->spots as $spot) {
                $save($spot, 'spots', $this->cards->forSpot($spot, $river, $river->region, $river->spots));
            }
        }

        foreach (BathingWater::query()->beaches()->whereNotNull('region_id')->with('region')->get() as $beach) {
            $save($beach, 'beaches', $this->cards->forBeach($beach, $beach->region));
        }

        foreach (Lake::query()->with('region')->get() as $lake) {
            $save($lake, 'lakes', $this->cards->forLake($lake, $lake->region));
        }

        $counts['removed'] = $existing->keys()->reject(fn (string $key) => isset($seen[$key]))
            ->each(fn (string $key) => $existing[$key]->delete())
            ->count();
        $this->pruneOldFiles();

        return $counts;
    }

    /**
     * Deletes card files no place points at any more, once they are a day old.
     */
    private function pruneOldFiles(): void
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
}
