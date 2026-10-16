<?php

namespace App\Console\Commands;

use App\Actions\Ingest\SyncSepaBathingWaterSamples;
use App\Services\Conditions\ChangedRegions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class IngestSepaBathingSamples extends Command
{
    protected $signature = 'ingest:sepa-bathing-samples';

    protected $description = 'Read the Scottish bathing water results pages that are due, a couple of sites per run, while switched on';

    public function handle(SyncSepaBathingWaterSamples $sync, ChangedRegions $changed): int
    {
        $result = $sync->handle();

        if ($result['skipped'] !== null) {
            $this->line("SEPA bathing samples skipped: {$result['skipped']}.");

            return self::SUCCESS;
        }

        if ($result['regions'] !== []) {
            $changed->mark($result['regions']);
        }

        $this->info(sprintf('%d SEPA results pages read, %d new samples.', $result['checked'], $result['samples']));

        if ($result['failed'] !== null) {
            $this->error("SEPA results page request failed, {$result['failed']}");
            Log::error("SEPA results page request failed: {$result['failed']}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
