<?php

namespace App\Jobs;

use App\Actions\ShareImages\GenerateShareImages;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;

/**
 * Forgets the cards of places without a page, then queues every page's card as a batch of DrawShareCards jobs, each
 * drawing only the cards that changed; old files are pruned once the batch is done. Dispatched after each deploy and
 * hourly, so a deploy never waits on cards: until a page's card is redrawn it keeps its old one.
 */
class QueueShareCards implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public bool $force = false)
    {
    }

    public function handle(GenerateShareImages $generate): void
    {
        $generate->forgetPlacesWithoutPages();

        $jobs = array_map(fn (array $batch) => new DrawShareCards($batch[0], $batch[1], $this->force), $generate->batches());

        if ($jobs === []) {
            $generate->pruneOldFiles();

            return;
        }

        Bus::batch($jobs)
            ->name('Share cards')
            ->allowFailures()
            ->finally(static fn () => app(GenerateShareImages::class)->pruneOldFiles())
            ->dispatch();
    }
}
