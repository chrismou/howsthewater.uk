<?php

namespace App\Services\Conditions;

use App\Data\BathingWaterSummary;
use App\Data\BeachConditions;
use App\Data\FloodWarningSummary;
use App\Data\Headline;
use App\Data\LakeConditions;
use App\Data\LevelSummary;
use App\Data\NearbyMonitorSummary;
use App\Data\RainfallSummary;
use App\Data\SpillHistoryDay;
use App\Data\SpotConditions;
use App\Data\UpstreamMonitorSummary;
use App\Data\WeatherHour;
use App\Data\WeatherSummary;
use App\Enums\BathingWaterClassification;
use App\Enums\LevelTrend;
use App\Enums\SpillRecency;
use App\Models\BathingWater;
use App\Models\SpillEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Writes the headline under a spot, beach or lake heading: the overflow state in plain words, the level against its
 * usual range, a flood warning or bathing water result where there is one, and notes when rain, wind or thunder
 * cross a threshold. It states what the data says, never whether a place is safe.
 */
class PlaceHeadline
{
    /** Rain over a day that makes rivers rise noticeably. */
    public const HEAVY_RAIN_MILLIMETRES = 10.0;

    /** The Met Office's threshold for heavy rain in an hour. */
    public const HEAVY_RAIN_MILLIMETRES_PER_HOUR = 4.0;

    public const STRONG_GUSTS_MPH = 35.0;

    /** How far ahead the forecast notes look. */
    public const FORECAST_HOURS = 12;

    /** Days with spills in the last 30 that make a quiet couple of days worth qualifying. */
    public const BUSY_MONTH_DAYS = 3;

    public function forSpot(SpotConditions $conditions, ?WeatherSummary $weather): Headline
    {
        $river = $conditions->spot->river;
        $monitors = $conditions->upstreamMonitors;
        $covered = $conditions->spillRecency !== SpillRecency::NotCovered;

        $sentences = array_values(array_filter([
            ...$this->bathingWaterSentences($conditions->bathingWater),
            $this->overflowSentence($conditions->spillRecency, 'upstream', $monitors->count(), $conditions->offlineCount, $conditions->dischargingNowCount, $conditions->spillsLast48HoursCount, $conditions->recentSpillEvents, $conditions->dailyHistory->filter(fn (SpillHistoryDay $day) => $day->spillCount > 0)->count(), $conditions->overflowDataIsStale(), $conditions->dataUpdatedAt, $this->companies($monitors)),
            $this->levelSentence('The '.$this->shortRiverName($river->name), $conditions->level),
            $this->floodSentence($conditions->floodWarnings),
        ]));

        [$notes, $forecastAt] = $this->notes($conditions->rainfall, $weather, 'rivers can rise quickly and run faster, and overflows spill most after rain', windy: false);

        return new Headline(
            sentences: $sentences,
            notes: $notes,
            overflowSource: $covered && $monitors->isNotEmpty() ? $this->monitorCount($monitors, 'upstream') : null,
            overflowAt: $conditions->dataUpdatedAt,
            levelSource: $this->levelSource($conditions->level),
            levelAt: $conditions->level?->latestAt,
            forecastAt: $forecastAt,
        );
    }

    public function forBeach(BeachConditions $conditions, ?WeatherSummary $weather): Headline
    {
        $overflows = $conditions->overflows;

        $sentences = array_values(array_filter([
            ...$this->bathingWaterSentences($conditions->bathingWater),
            $this->overflowSentence($overflows->spillRecency, $this->within($overflows->radiusMetres), $overflows->monitors->count(), $overflows->offlineCount, $overflows->dischargingNowCount, $overflows->spillsLast48HoursCount, $overflows->recentSpillEvents, $overflows->daysWithSpillsLast30, $overflows->isStale(), $overflows->dataUpdatedAt, $this->companies($overflows->monitors)),
        ]));

        [$notes, $forecastAt] = $this->notes($conditions->rainfall, $weather, 'overflows spill most after rain, and it can lower water quality for a day or two', windy: true);

        return new Headline(
            sentences: $sentences,
            notes: $notes,
            overflowSource: $overflows->isCovered() && $overflows->monitors->isNotEmpty() ? $this->monitorCount($overflows->monitors, $this->within($overflows->radiusMetres)) : null,
            overflowAt: $overflows->dataUpdatedAt,
            levelSource: null,
            levelAt: null,
            forecastAt: $forecastAt,
        );
    }

    public function forLake(LakeConditions $conditions, ?WeatherSummary $weather): Headline
    {
        $overflows = $conditions->overflows;

        $sentences = array_values(array_filter([
            $this->lakeClassificationSentence($conditions->bathingWaters),
            $this->overflowSentence($overflows->spillRecency, $this->within($overflows->radiusMetres), $overflows->monitors->count(), $overflows->offlineCount, $overflows->dischargingNowCount, $overflows->spillsLast48HoursCount, $overflows->recentSpillEvents, $overflows->daysWithSpillsLast30, $overflows->isStale(), $overflows->dataUpdatedAt, $this->companies($overflows->monitors)),
            $this->levelSentence($conditions->lake->name, $conditions->level),
        ]));

        [$notes, $forecastAt] = $this->notes($conditions->rainfall, $weather, 'overflows spill most after rain, and it can lower water quality for a day or two', windy: true);

        return new Headline(
            sentences: $sentences,
            notes: $notes,
            overflowSource: $overflows->isCovered() && $overflows->monitors->isNotEmpty() ? $this->monitorCount($overflows->monitors, $this->within($overflows->radiusMetres)) : null,
            overflowAt: $overflows->dataUpdatedAt,
            levelSource: $this->levelSource($conditions->level),
            levelAt: $conditions->level?->latestAt,
            forecastAt: $forecastAt,
        );
    }

    /**
     * "Poor bathing water (2025 classification)." and today's pollution risk forecast while one is in force, for a
     * beach or a spot with a designated bathing water. Nothing for a place without one.
     *
     * @return list<list<array{0: string, 1: bool}>|null>
     */
    private function bathingWaterSentences(?BathingWaterSummary $summary): array
    {
        $bathingWater = $summary?->bathingWater;
        $risk = $summary?->currentRisk();

        return [
            $bathingWater?->classification === null ? null : [
                [$bathingWater->classification->value, true],
                [' bathing water'.($bathingWater->classification_year ? " ({$bathingWater->classification_year} classification)." : '.'), false],
            ],
            $risk === null ? null : [[$risk->label().' today', true], ['.', false]],
        ];
    }

    /**
     * A lake's bathing water classifications: as for a beach when it has one site or all its sites share one, else
     * the count of sites per classification ("3 bathing water sites (2025 classifications): 2 Excellent, 1 Poor.").
     *
     * @param  Collection<int, BathingWaterSummary>  $sites
     * @return list<array{0: string, 1: bool}>|null
     */
    private function lakeClassificationSentence(Collection $sites): ?array
    {
        $classified = $sites->map(fn (BathingWaterSummary $site) => $site->bathingWater)->filter(fn (BathingWater $site) => $site->classification !== null);

        if ($classified->isEmpty()) {
            return null;
        }

        $years = $classified->pluck('classification_year')->filter()->unique();
        $year = $years->count() === 1 ? $years->first() : null;
        $counts = $classified->countBy(fn (BathingWater $site) => $site->classification->value);

        if ($counts->count() === 1) {
            $sites = $classified->count() === 1 ? '' : " at all {$classified->count()} sites";

            return [
                [$counts->keys()->first(), true],
                [" bathing water{$sites}".($year ? " ({$year} ".Str::plural('classification', $classified->count()).')' : '').'.', false],
            ];
        }

        $breakdown = collect(BathingWaterClassification::cases())
            ->filter(fn (BathingWaterClassification $classification) => $counts->has($classification->value))
            ->map(fn (BathingWaterClassification $classification) => $counts[$classification->value].' '.$classification->value)
            ->join(', ');

        return [
            ["{$classified->count()} bathing water sites", false],
            [($year ? " ({$year} classifications)" : '').': ', false],
            [$breakdown, true],
            ['.', false],
        ];
    }

    /**
     * The overflow state in a sentence: what is spilling now, when the last spill ended, or that the data is missing,
     * out of date or not read here. Missing data never reads as no spills.
     *
     * @param  string  $where  "upstream" or "within 3 km"
     * @param  Collection<int, SpillEvent>  $events  The last 30 days' spills
     * @param  int  $daysWithSpills  UK days in the last 30 with a counted spill running
     * @return list<array{0: string, 1: bool}>
     */
    private function overflowSentence(SpillRecency $recency, string $where, int $monitorCount, int $offline, int $dischargingNow, int $spillsLast48Hours, Collection $events, int $daysWithSpills, bool $stale, ?Carbon $updatedAt, string $companies): array
    {
        $upstream = $where === 'upstream';
        $spills = fn (int $count) => $count.' '.Str::plural('spill', $count).' in the last 48 hours';

        if ($recency === SpillRecency::NotCovered) {
            return [["Overflow data isn't available here yet", true], ['.', false]];
        }

        if ($stale && $monitorCount > 0) {
            if ($updatedAt === null) {
                return [['Overflow data is out of date', true], ['.', false]];
            }

            $date = $updatedAt->copy()->timezone($this->timezone());

            return [
                ['Overflow data is out of date', true],
                [": the latest from {$companies} is from ", false],
                [$date->format($date->isSameYear(now()) ? 'j F' : 'j F Y'), true],
                ['.', false],
            ];
        }

        // Some monitors offline among working ones: said plainly after the state, so the count is never hidden.
        $offlineNote = $offline > 0 && $recency !== SpillRecency::Offline
            ? [[" {$offline} ".Str::plural('monitor', $offline).' '.($offline === 1 ? 'is' : 'are').' offline.', false]]
            : [];
        $working = $monitorCount - $offline;

        // With half or more offline, a quiet couple of days leads with how few are reporting, not with "no spills".
        if ($recency === SpillRecency::None && $offline > 0 && $offline >= $working) {
            return [
                ["Only {$working} of {$monitorCount} monitors {$where} ".($working === 1 ? 'is' : 'are').' reporting', true],
                [', and '.($working === 1 ? 'it' : 'they').' reported no spills in the last 48 hours.', false],
            ];
        }

        return [...match ($recency) {
            SpillRecency::NoData => [['No monitored overflows '.$where, true], ['.', false]],
            SpillRecency::Offline => [
                [$upstream ? 'Upstream monitors are offline' : "Monitors {$where} are offline", true],
                [', so there is ', false],
                ['no current spill data', true],
                ['.', false],
            ],
            SpillRecency::DischargingNow => [
                ["{$dischargingNow} ".Str::plural('overflow', $dischargingNow)." {$where} ".($dischargingNow === 1 ? 'is' : 'are').' discharging now', true],
                ...($spillsLast48Hours > 0 ? [[', with ', false], [$spills($spillsLast48Hours), true]] : []),
                ['.', false],
            ],
            SpillRecency::Within24Hours, SpillRecency::Within48Hours => $this->lastSpillSentence($upstream ? 'Last upstream spill' : "Last spill {$where}", $events, $spillsLast48Hours, $spills),
            default => [
                ["No spills reported {$where} in the last 48 hours", true],
                ...($daysWithSpills >= self::BUSY_MONTH_DAYS ? [[', but ', false], ['spills on '.$daysWithSpills.' of the last 30 days', true]] : []),
                ['.', false],
            ],
        }, ...$offlineNote];
    }

    /**
     * @param  Collection<int, SpillEvent>  $events
     * @param  callable(int): string  $spills
     * @return list<array{0: string, 1: bool}>
     */
    private function lastSpillSentence(string $lastSpill, Collection $events, int $spillsLast48Hours, callable $spills): array
    {
        $lastEnded = $events->pluck('ended_at')->filter()->max();

        if ($lastEnded === null) {
            return [[ucfirst($spills($spillsLast48Hours)), true], ['.', false]];
        }

        return [
            ["{$lastSpill} ended ".$lastEnded->diffForHumans(), true],
            ...($spillsLast48Hours > 1 ? [[', with ', false], [$spillsLast48Hours.' in the last 48 hours', true]] : []),
            ['.', false],
        ];
    }

    /**
     * "The Wharfe water level is normal and steady.", or when the gauge last reported if that was a while ago.
     * Nothing when there is no gauge or no reading.
     *
     * @return list<array{0: string, 1: bool}>|null
     */
    private function levelSentence(string $subject, ?LevelSummary $level): ?array
    {
        if ($level === null || $level->latestValue === null || $level->latestAt === null) {
            return null;
        }

        if ($level->isStale()) {
            $readAt = $level->latestAt->copy()->timezone($this->timezone());

            return [["The {$level->station->label} gauge ", false], ['has not reported since '.$readAt->format($readAt->isSameDay(now($this->timezone())) ? 'H:i' : 'j F'), true], ['.', false]];
        }

        // The subject already says "water level", so "at its usual level" would repeat it.
        $relative = $level->relativeToTypical === 'within typical range' ? 'normal' : $level->relativeToUsual();
        $trend = $level->trend === LevelTrend::Unknown ? null : strtolower($level->trend->label());

        if ($relative === null && $trend === null) {
            return null;
        }

        return [
            ["{$subject} water level is ", false],
            ...($relative !== null ? [[$relative, true]] : []),
            ...($relative !== null && $trend !== null ? [[' and ', false]] : []),
            ...($trend !== null ? [[$trend, true]] : []),
            ['.', false],
        ];
    }

    /**
     * @param  Collection<int, FloodWarningSummary>  $warnings  Most severe first
     * @return list<array{0: string, 1: bool}>|null
     */
    private function floodSentence(Collection $warnings): ?array
    {
        $warning = $warnings->first();

        return $warning === null ? null : [[$warning->severity->label().' in force', true], ['.', false]];
    }

    /**
     * Notes for rain that has fallen, and for heavy rain, thunder or (on open water) strong winds forecast in the
     * next twelve hours, with when the forecast behind any forecast note was read, for the source line. A quiet day
     * has none.
     *
     * @return array{0: list<list<array{0: string, 1: bool}>>, 1: Carbon|null}
     */
    private function notes(?RainfallSummary $rainfall, ?WeatherSummary $weather, string $rainEffect, bool $windy): array
    {
        $notes = [];

        if ($rainfall !== null && $rainfall->hasReadings() && ! $rainfall->isStale() && $rainfall->last24HoursMillimetres >= self::HEAVY_RAIN_MILLIMETRES) {
            $notes[] = [
                ['Heavy rain in the last 24 hours', true],
                [' ('.number_format($rainfall->last24HoursMillimetres, 0)." mm): {$rainEffect}.", false],
            ];
        }

        if ($weather === null || $weather->isStale()) {
            return [$notes, null];
        }

        $gaugeNotes = count($notes);

        $hours = collect([$weather->now])->concat($weather->laterHours)
            ->filter(fn (WeatherHour $hour) => $hour->at->lessThan($weather->now->at->copy()->addHours(self::FORECAST_HOURS)));
        $from = fn (WeatherHour $hour) => $hour->at->equalTo($weather->now->at) ? 'now' : 'from around '.$hour->at->copy()->timezone($this->timezone())->format('H:i');

        $thunder = $hours->first(fn (WeatherHour $hour) => $hour->icon() === 'thunder');

        if ($thunder !== null) {
            $notes[] = [['Thunderstorms forecast', true], [' '.$from($thunder).'. Get out of the water if you hear thunder!', false]];
        }

        $heavyHour = $hours->first(fn (WeatherHour $hour) => ($hour->precipitation ?? 0) >= self::HEAVY_RAIN_MILLIMETRES_PER_HOUR);
        $heavyTotal = $hours->sum(fn (WeatherHour $hour) => $hour->precipitation ?? 0) >= self::HEAVY_RAIN_MILLIMETRES;
        $rainStarts = $heavyHour ?? ($heavyTotal ? $hours->first(fn (WeatherHour $hour) => ($hour->precipitation ?? 0) >= 0.5) : null);

        if ($rainStarts !== null) {
            $notes[] = [['Heavy rain forecast', true], [' '.$from($rainStarts).'.', false]];
        }

        $strongestGust = $hours->max(fn (WeatherHour $hour) => $hour->windGusts ?? 0);

        if ($windy && $strongestGust >= self::STRONG_GUSTS_MPH) {
            $notes[] = [['Strong winds forecast', true], [', gusting to '.number_format($strongestGust, 0).' mph: open water can get rough.', false]];
        }

        return [$notes, count($notes) > $gaugeNotes ? $weather->fetchedAt : null];
    }

    /**
     * "9 Yorkshire Water monitors upstream".
     *
     * @template TMonitor of UpstreamMonitorSummary|NearbyMonitorSummary
     *
     * @param  Collection<int, TMonitor>  $monitors
     */
    private function monitorCount(Collection $monitors, string $where): string
    {
        return $monitors->count().' '.$this->companies($monitors).' '.Str::plural('monitor', $monitors->count()).' '.$where;
    }

    /**
     * The water companies by name, without "Ltd" or "Services": "Yorkshire Water".
     *
     * @template TMonitor of UpstreamMonitorSummary|NearbyMonitorSummary
     *
     * @param  Collection<int, TMonitor>  $monitors
     */
    private function companies(Collection $monitors): string
    {
        return $monitors
            ->map(fn (UpstreamMonitorSummary|NearbyMonitorSummary $summary) => preg_replace(['/\s+(Ltd|Limited|plc)\.?$/i', '/\s+Services$/i'], '', $summary->monitor->company))
            ->unique()
            ->sort()
            ->join(', ', ' and ');
    }

    private function levelSource(?LevelSummary $level): ?string
    {
        return $level?->latestAt === null ? null : "the {$level->station->label} gauge";
    }

    private function within(int $radiusMetres): string
    {
        return 'within '.($radiusMetres / 1000).' km';
    }

    /**
     * "The Wharfe" for the River Wharfe, "The Teifi" for Afon Teifi; other names stay whole ("The Water of Leith").
     */
    private function shortRiverName(string $name): string
    {
        return preg_replace('/^(River|Afon)\s+/', '', $name);
    }

    private function timezone(): string
    {
        return config('app.display_timezone', 'Europe/London');
    }
}
