<?php

namespace App\Data;

use Illuminate\Support\Carbon;

/**
 * A day's first light (civil dawn), sunrise, sunset and last light (civil dusk) at a place, worked out from its
 * position, so they need no data source. Any can be null far enough north in midsummer, where the sky never gets
 * dark enough for civil dusk.
 */
final readonly class SunTimes
{
    public function __construct(
        public ?Carbon $firstLight,
        public ?Carbon $sunrise,
        public ?Carbon $sunset,
        public ?Carbon $lastLight,
    ) {
    }

    public static function at(float $latitude, float $longitude, ?Carbon $day = null): self
    {
        $timezone = config('app.display_timezone', 'Europe/London');
        $noon = ($day ?? now())->copy()->timezone($timezone)->setTime(12, 0);
        $info = date_sun_info($noon->getTimestamp(), $latitude, $longitude);
        $time = fn (string $key): ?Carbon => is_int($info[$key] ?? null) ? Carbon::createFromTimestamp($info[$key], $timezone) : null;

        return new self(
            firstLight: $time('civil_twilight_begin'),
            sunrise: $time('sunrise'),
            sunset: $time('sunset'),
            lastLight: $time('civil_twilight_end'),
        );
    }

    /**
     * Whether a moment is between sunset and sunrise, by time of day, so an hour after midnight counts as dark too.
     * Where the sun does not set or rise, it is never dark.
     */
    public function isDark(Carbon $at): bool
    {
        if ($this->sunrise === null || $this->sunset === null) {
            return false;
        }

        $timeOfDay = $at->copy()->timezone($this->sunrise->timezone)->format('H:i');

        return $timeOfDay < $this->sunrise->format('H:i') || $timeOfDay >= $this->sunset->format('H:i');
    }
}
