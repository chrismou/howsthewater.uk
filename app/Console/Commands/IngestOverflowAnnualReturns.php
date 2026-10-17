<?php

namespace App\Console\Commands;

use App\Actions\Ingest\ImportOverflowAnnualReturns;
use App\Models\OverflowAnnualReturn;
use Illuminate\Console\Command;

class IngestOverflowAnnualReturns extends Command
{
    protected $signature = 'ingest:overflow-annual-returns {--if-empty : Only import when no returns are stored yet (run on deploy)}';

    protected $description = 'Import the storm overflow annual returns and match them to monitors';

    public function handle(ImportOverflowAnnualReturns $import): int
    {
        if ($this->option('if-empty') && OverflowAnnualReturn::query()->exists()) {
            $this->line('Annual returns already imported.');

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
