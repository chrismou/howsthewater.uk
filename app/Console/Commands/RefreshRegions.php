<?php

namespace App\Console\Commands;

use App\Actions\Ingest\RefreshRegions as RefreshRegionsAction;
use App\Models\Region;
use App\Services\Conditions\ChangedRegions;
use Illuminate\Console\Command;

class RefreshRegions extends Command
{
    protected $signature = 'regions:refresh {--all : Refresh every region, not just those an ingest marked changed}';

    protected $description = 'Rebuild cached conditions, region summaries and menus for regions whose data changed';

    public function handle(RefreshRegionsAction $refresh, ChangedRegions $changed): int
    {
        $regionIds = $this->option('all') ? Region::query()->pluck('id')->all() : $changed->take();

        if ($regionIds === []) {
            $this->line('No regions changed since the last refresh.');

            return self::SUCCESS;
        }

        $result = $refresh->handle($regionIds);

        $this->info(sprintf('Refreshed %d regions: %d spots, %d beaches and %d lakes.', $result['regions'], $result['spots'], $result['beaches'], $result['lakes']));

        return self::SUCCESS;
    }
}
