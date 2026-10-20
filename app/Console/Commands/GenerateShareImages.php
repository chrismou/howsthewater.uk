<?php

namespace App\Console\Commands;

use App\Actions\ShareImages\GenerateShareImages as GenerateShareImagesAction;
use App\Services\ShareImages\ShareCardRenderer;
use App\Services\ShareImages\ShareCards;
use Illuminate\Console\Command;

class GenerateShareImages extends Command
{
    protected $signature = 'share-images:generate
        {--force : Redraw every card, including those that have not changed}
        {--overflows : Draw overflow pages\' cards instead (scheduled), at most --limit a run}
        {--limit=500 : With --overflows, the most cards to draw in one run}
        {--within= : Stop drawing place cards after this many seconds, leaving the rest to the next run}
        {--site : Redraw the site\'s own card, public/og-image.png, instead (run locally and commit it, as releases are read-only)}';

    protected $description = 'Draw the share card for every spot, river, beach and lake page (or, with --overflows, overflow page) whose card has changed';

    public function handle(GenerateShareImagesAction $generate, ShareCards $cards, ShareCardRenderer $renderer): int
    {
        if ($this->option('site')) {
            file_put_contents(public_path('og-image.png'), $renderer->render($cards->forSite()));
            $this->info('Drew public/og-image.png.');

            return self::SUCCESS;
        }

        if ($this->option('overflows')) {
            $result = $generate->handleOverflows(max(1, (int) $this->option('limit')), (bool) $this->option('force'));
            $this->info(sprintf('Drew %d overflow share cards, %d unchanged, %d removed, %d waiting for the next run.', $result['drawn'], $result['unchanged'], $result['removed'], $result['waiting']));

            return self::SUCCESS;
        }

        $within = $this->option('within');
        $result = $generate->handle((bool) $this->option('force'), $within === null ? null : max(0, (int) $within));

        $this->info(sprintf('Drew %d share cards, %d unchanged, %d removed.', $result['drawn'], $result['unchanged'], $result['removed'])
            .($result['waiting'] > 0 ? " {$result['waiting']} waiting for the next run." : ''));

        return self::SUCCESS;
    }
}
