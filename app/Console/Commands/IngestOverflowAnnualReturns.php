<?php

namespace App\Console\Commands;

use App\Actions\Ingest\ImportOverflowAnnualReturns;
use Illuminate\Console\Command;

class IngestOverflowAnnualReturns extends Command
{
    protected $signature = 'ingest:overflow-annual-returns {--if-outdated : Only import when none are stored, or they were matched by older rules (run on deploy)}';

    protected $description = 'Import the storm overflow annual returns and match them to monitors';

    public function handle(ImportOverflowAnnualReturns $import): int
    {
        if ($this->option('if-outdated') && $import->isCurrent()) {
            $this->line('Annual returns already imported and matched.');

            return self::SUCCESS;
        }

        $result = $import->handle();

        $this->info(sprintf('Imported %d years: %d rows, %d matched to monitors.', $result['years'], $result['rows'], $result['matched']));

        foreach ($result['failed'] as $year) {
            $this->error("The {$year} returns failed; that year's rows were kept as they were.");
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
