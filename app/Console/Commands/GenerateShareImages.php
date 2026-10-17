<?php

namespace App\Console\Commands;

use App\Actions\ShareImages\GenerateShareImages as GenerateShareImagesAction;
use Illuminate\Console\Command;

class GenerateShareImages extends Command
{
    protected $signature = 'share-images:generate {--force : Redraw every card, including those that have not changed}';

    protected $description = 'Draw the share card for every spot, river, beach and lake page whose card has changed';

    public function handle(GenerateShareImagesAction $generate): int
    {
        $result = $generate->handle((bool) $this->option('force'));

        $this->info(sprintf('Drew %d share cards, %d unchanged, %d removed.', $result['drawn'], $result['unchanged'], $result['removed']));

        return self::SUCCESS;
    }
}
