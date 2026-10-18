<?php

namespace App\Console\Commands;

use App\Actions\Ingest\ImportOverflowSpillLogs;
use Illuminate\Console\Command;

class IngestOverflowSpillLogs extends Command
{
    protected $signature = 'ingest:overflow-spill-logs {--force : Read every source again, even those that have not changed}';

    protected $description = 'Import the spills the water companies published after the fact, for the time before each overflow\'s live record';

    public function handle(ImportOverflowSpillLogs $import): int
    {
        $result = $import->handle((bool) $this->option('force'));

        $this->info(sprintf(
            'Read %d spill logs (%d unchanged): %d spills imported, %d rows for overflows we do not hold, %d rejected as implausible.',
            $result['read'],
            $result['unchanged'],
            $result['spills'],
            $result['unmatched'],
            $result['rejected'],
        ));

        foreach ($result['failed'] as $source) {
            $this->error("{$source} failed; it is read again on the next run, and its months show as no data until then.");
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
