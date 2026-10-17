<?php

namespace App\Actions\Ingest;

use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\Spot;
use App\Models\WeatherForecast;
use App\Services\Weather\OpenMeteoClient;
use App\Services\Weather\WeatherCell;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Reads Open-Meteo's forecast for every grid cell holding a spot, beach or lake with a page, a batch of cells per
 * request with a pause between batches, so a run never comes near the API's per-minute allowance. A cell read in the
 * last two and a half hours is skipped, so a manual run straight after the scheduled one costs nothing. A failed
 * request is logged and ends the run, leaving every cell's last forecast in place for the next run to retry.
 */
class SyncWeatherForecasts
{
    public const REFRESH_AFTER_MINUTES = 150;

    /** At most 25 calls every 4 seconds is 375 a minute, under the 400 the client allows itself. */
    private const PAUSE_SECONDS = 4;

    public function __construct(private OpenMeteoClient $client)
    {
    }

    /**
     * @return array{cells: int, fetched: int, failed: bool}
     */
    public function handle(bool $force = false): array
    {
        $cells = $this->cellsInUse();
        $fresh = $force ? collect() : WeatherForecast::query()
            ->where('fetched_at', '>=', now()->subMinutes(self::REFRESH_AFTER_MINUTES))
            ->pluck('cell_key')
            ->flip();
        $due = $cells->reject(fn (WeatherCell $cell) => $fresh->has($cell->key))->values();
        $fetched = 0;

        foreach ($due->chunk(OpenMeteoClient::BATCH_SIZE)->values() as $index => $batch) {
            if ($index > 0) {
                Sleep::for(self::PAUSE_SECONDS)->seconds();
            }

            try {
                $forecasts = $this->client->forecasts($batch->values()->all());
            } catch (ConnectionException|RequestException|RuntimeException $exception) {
                Log::error('Open-Meteo weather forecast request failed: '.$exception->getMessage());

                return ['cells' => $cells->count(), 'fetched' => $fetched, 'failed' => true];
            }

            foreach ($batch->values() as $position => $cell) {
                $this->store($cell, $forecasts[$position]);
                $fetched++;
            }
        }

        return ['cells' => $cells->count(), 'fetched' => $fetched, 'failed' => false];
    }

    /**
     * Every cell holding a published spot on a main river, a beach in a region or a lake.
     *
     * @return Collection<int, WeatherCell>
     */
    private function cellsInUse(): Collection
    {
        $points = Spot::query()->published()->onMainRiver()->get(['latitude', 'longitude'])
            ->concat(BathingWater::query()->beaches()->whereNotNull('region_id')->get(['latitude', 'longitude']))
            ->concat(Lake::query()->get(['latitude', 'longitude']));

        return $points
            ->map(fn (Spot|BathingWater|Lake $place) => WeatherCell::containing($place->latitude, $place->longitude))
            ->unique('key')
            ->sortBy('key')
            ->values();
    }

    /**
     * @param  array{hours: list<array{at: int, temperature: float|null, apparent_temperature: float|null, precipitation_probability: int|null, weather_code: int|null, wind_speed: float|null, wind_gusts: float|null, wind_direction: int|null}>, uv_index_max: array<string, float>}  $forecast
     */
    private function store(WeatherCell $cell, array $forecast): void
    {
        WeatherForecast::query()->updateOrCreate(['cell_key' => $cell->key], [
            'latitude' => $cell->latitude,
            'longitude' => $cell->longitude,
            'data' => $forecast,
            'forecast_at' => isset($forecast['hours'][0]) ? now()->setTimestamp($forecast['hours'][0]['at']) : now(),
            'fetched_at' => now(),
        ]);
    }
}
