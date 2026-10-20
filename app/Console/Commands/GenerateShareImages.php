<?php

namespace App\Console\Commands;

use App\Actions\ShareImages\GenerateShareImages as GenerateShareImagesAction;
use App\Jobs\QueueShareCards;
use App\Services\ShareImages\ShareCardRenderer;
use App\Services\ShareImages\ShareCards;
use Illuminate\Console\Command;

class GenerateShareImages extends Command
{
    protected $signature = 'share-images:generate
        {--force : Redraw every card, including those that have not changed}
        {--queue : Queue the drawing in batches for the queue worker instead (after deploys, and hourly)}
        {--site : Redraw the site\'s own card, public/og-image.png, instead (run locally and commit it, as releases are read-only)}';

    protected $description = 'Draw the share card for every spot, river, beach, lake and overflow page whose card has changed';

    public function handle(GenerateShareImagesAction $generate, ShareCards $cards, ShareCardRenderer $renderer): int
    {
        if ($this->option('site')) {
            file_put_contents(public_path('og-image.png'), $renderer->render($cards->forSite()));
            $this->info('Drew public/og-image.png.');

            return self::SUCCESS;
        }

        if ($this->option('queue')) {
            QueueShareCards::dispatch((bool) $this->option('force'));
            $this->info('Queued the share cards.');

            return self::SUCCESS;
        }

        $result = $generate->handle((bool) $this->option('force'));
        $this->info(sprintf('Drew %d share cards, %d unchanged, %d removed.', $result['drawn'], $result['unchanged'], $result['removed']));

        return self::SUCCESS;
    }
}
