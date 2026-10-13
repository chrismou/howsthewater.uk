<?php

namespace App\Console\Commands;

use App\Actions\Rivers\AssignRiverPositions as AssignRiverPositionsAction;
use App\Models\River;
use Illuminate\Console\Command;

class AssignRiverPositions extends Command
{
    protected $signature = 'rivers:positions {river? : Slug of a single river to process}';

    protected $description = 'Position overflow monitors, gauges and spots along each river centreline';

    public function handle(AssignRiverPositionsAction $assign): int
    {
        $rivers = River::query()
            ->when($this->argument('river'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get();

        foreach ($rivers as $river) {
            $result = $assign->handle($river);

            $this->info(sprintf(
                '%s: %d monitors, %d gauges, %d spots positioned.',
                $river->name,
                $result['monitors'],
                $result['gauges'],
                $result['spots'],
            ));
        }

        return self::SUCCESS;
    }
}
