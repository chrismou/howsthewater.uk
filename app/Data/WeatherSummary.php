<?php

namespace App\Data;

use App\Models\WeatherForecast;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The weather at a place now and over the next hours, from its grid cell's
 * forecast. "Now" is the forecast hour the page is viewed in, so it moves on
 * between the three-hourly reads.
 */
final readonly class WeatherSummary
{
    /** Two missed reads in a row. */
    public const STALE_AFTER_HOURS = 6;

    /**
     * @param  Collection<int, WeatherHour>  $laterHours  The hours after now, in order
     */
    public function __construct(
        public WeatherHour $now,
        public Collection $laterHours,
        public ?float $uvIndexMax,
        public Carbon $fetchedAt,
    ) {
    }

    /**
     * Null when every hour in the forecast has passed.
     */
    public static function fromForecast(WeatherForecast $forecast): ?self
    {
        $hours = collect($forecast->data['hours'])->map(fn (array $hour) => WeatherHour::fromArray($hour));
        $current = $hours->last(fn (WeatherHour $hour) => $hour->at->lessThanOrEqualTo(now()));

        if ($current === null || $current->at->lessThan(now()->subHour())) {
            return null;
        }

        return new self(
            now: $current,
            laterHours: $hours->filter(fn (WeatherHour $hour) => $hour->at->greaterThan($current->at))->values(),
            uvIndexMax: $forecast->data['uv_index_max'][now(config('app.display_timezone', 'Europe/London'))->toDateString()] ?? null,
            fetchedAt: $forecast->fetched_at,
        );
    }

    /**
     * A few later hours, spaced out, for a glance at how the day goes.
     *
     * @return Collection<int, WeatherHour>
     */
    public function outlook(int $everyHours = 2, int $count = 5): Collection
    {
        return $this->laterHours
            ->filter(fn (WeatherHour $hour) => (int) round($this->now->at->diffInHours($hour->at)) % $everyHours === 0)
            ->take($count)
            ->values();
    }

    /**
     * The World Health Organization's band for today's highest UV index.
     */
    public function uvBand(): ?string
    {
        return match (true) {
            $this->uvIndexMax === null => null,
            $this->uvIndexMax < 3 => 'low',
            $this->uvIndexMax < 6 => 'moderate',
            $this->uvIndexMax < 8 => 'high',
            $this->uvIndexMax < 11 => 'very high',
            default => 'extreme',
        };
    }

    public function isStale(): bool
    {
        return $this->fetchedAt->lessThan(now()->subHours(self::STALE_AFTER_HOURS));
    }
}
