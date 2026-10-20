<?php

namespace App\Jobs;

use App\Actions\ShareImages\GenerateShareImages;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Draws the changed share cards of one batch of places (GenerateShareImages::batches()), queued by QueueShareCards.
 */
class DrawShareCards implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    /**
     * @param  string  $kind  rivers (with their spots), beaches, lakes or overflows
     * @param  list<int>  $ids
     */
    public function __construct(public string $kind, public array $ids, public bool $force = false)
    {
    }

    public function handle(GenerateShareImages $generate): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $generate->draw($this->kind, $this->ids, $this->force);
    }
}
