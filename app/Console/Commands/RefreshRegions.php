<?php

namespace App\Console\Commands;

use App\Actions\Ingest\RefreshRegions as RefreshRegionsAction;
use App\Models\Region;
use App\Services\Conditions\ChangedRegions;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class RefreshRegions extends Command
{
    protected $signature = 'regions:refresh {--all : Refresh every region, not just those an ingest marked changed}';

    protected $description = 'Rebuild cached conditions, region summaries and menus for regions whose data changed';

    public function handle(RefreshRegionsAction $refresh, ChangedRegions $changed): int
    {
        $marked = $changed->take();
        $regionIds = $this->option('all') ? Region::query()->pluck('id')->all() : $marked;

        if ($regionIds === []) {
            $this->line('No regions changed since the last refresh.');

            return self::SUCCESS;
        }

        try {
            $result = $refresh->handle($regionIds);
        } catch (Throwable $exception) {
            // Put the marks back, so the next scheduled run retries these regions.
            $changed->mark($regionIds);

            throw $exception;
        }

        $this->info(sprintf('Refreshed %d %s: %d spots, %d beaches and %d lakes.', $result['regions'], Str::plural('region', $result['regions']), $result['spots'], $result['beaches'], $result['lakes']));

        return self::SUCCESS;
    }
}
