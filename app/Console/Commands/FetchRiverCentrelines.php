<?php

namespace App\Console\Commands;

use App\Actions\Rivers\FetchRiverCentreline;
use App\Models\River;
use Illuminate\Console\Command;
use Throwable;

class FetchRiverCentrelines extends Command
{
    protected $signature = 'rivers:centrelines
        {river? : Slug of a single river to fetch}
        {--source=os : Where to read centrelines from: "os" (OS Open Rivers GeoPackage) or "osm" (OpenStreetMap)}
        {--file= : Path to the OS Open Rivers GeoPackage (defaults to services.ordnance_survey.open_rivers_path)}
        {--force : Refetch rivers that already have a centreline}';

    protected $description = 'Import river centrelines from OS Open Rivers or OpenStreetMap';

    public function handle(FetchRiverCentreline $fetch): int
    {
        $rivers = River::query()
            ->when($this->argument('river'), fn ($query, $slug) => $query->where('slug', $slug))
            ->orderBy('parent_river_id')
            ->orderBy('id')
            ->get();

        $source = $this->option('source');
        $file = $this->option('file') ?: config('services.ordnance_survey.open_rivers_path');

        foreach ($rivers as $river) {
            if ($river->hasCentreline() && ! $this->option('force')) {
                $this->line("{$river->name}: already has a centreline, skipping.", verbosity: 'v');

                continue;
            }

            try {
                $source === 'osm'
                    ? $fetch->fromOpenStreetMap($river)
                    : $fetch->fromOpenRivers($river, (string) $file);

                $this->info(sprintf('%s: %d points, %.1f km.', $river->name, count($river->centreline), $river->length_metres / 1000));
            } catch (Throwable $exception) {
                $this->error("{$river->name}: {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
