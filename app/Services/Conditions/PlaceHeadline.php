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
use App\Data\RiverSummary;
use App\Data\SpillHistoryDay;
use App\Data\SpotConditions;
use App\Data\SpotSummary;
use App\Data\UpstreamMonitorSummary;
use App\Data\WeatherHour;
use App\Data\WeatherSummary;
use App\Enums\BathingWaterClassification;
use App\Enums\LevelTrend;
use App\Enums\SpillRecency;
use App\Actions\Ingest\SyncFloodWarnings;
use App\Enums\Agency;
use App\Models\BathingWater;
use App\Models\SpillEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Writes the headline under a spot, beach or lake heading: the overflow state in plain words, the level against its
 * usual range, a flood warning or bathing water result where there is one, and notes when rain, wind or thunder
 * cross a threshold. A river's headline leads with flooding, as people search a river's levels when they fear one:
 * any alert or warning in force (or that none is), its gauges against their usual ranges and which way they are
 * going, then its overflows. It states what the data says, never whether a place is safe or will flood.
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
            $this->levelSentence('The '.$river->shortName(), $conditions->level),
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
     * The headline for a river page, from its cached summary. covered: whether we read the region's overflow data.
     */
    public function forRiver(RiverSummary $river, ?WeatherSummary $weather, bool $covered): Headline
    {
        $short = $this->shortRiverName($river->name);
        $family = $river->hasTributaries ? ' and its tributaries' : '';
        $gauged = $river->spotsWithOwnGauge()->filter(fn (SpotSummary $spot) => $spot->levelValue !== null && $spot->levelAt !== null);
        $current = $gauged->reject(fn (SpotSummary $spot) => $spot->levelIsStale())->values();
        $leadGauge = $this->leadGauge($current);

        $sentences = array_values(array_filter([
            $this->riverFloodSentence($river, $short),
            ...$this->riverLevelSentences($current, $gauged),
            $this->riverOverflowSentence($river, $short, $family, $covered),
        ]));

        $wettest = $river->spots
            ->filter(fn (SpotSummary $spot) => $spot->rainfallLast24HoursMillimetres !== null && $spot->rainfallAt !== null && $spot->rainfallAt->greaterThanOrEqualTo(now()->subHours(RainfallSummary::STALE_AFTER_HOURS)))
            ->sortByDesc('rainfallLast24HoursMillimetres')
            ->first();
        $notes = $this->rainNote($wettest?->rainfallLast24HoursMillimetres, $wettest === null ? null : "near {$wettest->name}", 'rivers usually keep rising for some hours after rain stops');
        [$weatherNotes, $forecastAt] = $this->weatherNotes($weather, windy: false);

        return new Headline(
            sentences: $sentences,
            notes: [...$notes, ...$weatherNotes],
            overflowSource: $covered && $river->monitorCount > 0 ? $river->monitorCount.' '.collect($river->companies)->join(', ', ' and ').' '.Str::plural('monitor', $river->monitorCount)." on the {$short}{$family}" : null,
            overflowAt: $river->dataUpdatedAt(),
            levelSource: $leadGauge === null ? null : "the {$leadGauge->gaugeLabel} gauge",
            levelAt: $leadGauge?->levelAt,
            forecastAt: $forecastAt,
        );
    }

    /**
     * The gauge the level sentence leads with and the source line names: the highest above its usual range, else
     * the one rising fastest, else the first along the river.
     *
     * @param  Collection<int, SpotSummary>  $current  Gauged spots with a current reading, source to mouth
     */
    private function leadGauge(Collection $current): ?SpotSummary
    {
        return $current->filter(fn (SpotSummary $spot) => $spot->isAboveTypicalRange())->sortByDesc('levelValue')->first()
            ?? $current->filter(fn (SpotSummary $spot) => $spot->levelTrend === LevelTrend::Rising)->first()
            ?? $current->first();
    }

    /**
     * Any flood alert or warning in force for the river's spots, or that none is, so long as flood areas cover the
     * river and its agency's feed was read recently. Nothing where no flood area is linked yet, as "none in force"
     * would then be a guess.
     *
     * @return list<array{0: string, 1: bool}>|null
     */
    private function riverFloodSentence(RiverSummary $river, string $short): ?array
    {
        $warnings = $river->floodWarnings();

        if ($warnings->isNotEmpty()) {
            $severities = $warnings->map(fn (FloodWarningSummary $warning) => $warning->severity)->unique()->values();
            $labels = $severities->map(fn ($severity) => strtolower($severity->label()));

            return [
                [ucfirst($labels->join(', ', ' and ')).' in force', true],
                [$warnings->count() === 1 ? " for {$warnings->first()->areaLabel}." : " for {$warnings->count()} areas along the {$short}.", false],
            ];
        }

        if ($river->floodAreaAgencies === []) {
            return null;
        }

        $checkedAt = collect($river->floodAreaAgencies)->map(fn (Agency $agency) => SyncFloodWarnings::lastCheckedAt($agency));

        if ($checkedAt->contains(null)) {
            return [['Flood warnings have not been checked yet', true], ['.', false]];
        }

        $oldest = $checkedAt->min();

        if ($oldest->lessThan(now()->subHours(FloodWarningSummary::STALE_AFTER_HOURS))) {
            $time = $oldest->copy()->timezone($this->timezone());

            return [['Flood warnings were last checked at '.$time->format($time->isSameDay(now($this->timezone())) ? 'H:i' : 'H:i \o\n j F'), true], [', so this may be out of date.', false]];
        }

        return [['No flood alerts or warnings in force', true], [" for the {$short}.", false]];
    }

    /**
     * The river's gauges against their usual ranges, grouped: "Higher than usual at Callander and Doune, rising at
     * Callander (up 0.32 m in 3 hours). At its usual level at Stirling." A gauge with no reading for hours is said
     * to be silent instead, so a stale level never passes for the current one.
     *
     * @param  Collection<int, SpotSummary>  $current  Gauged spots with a current reading, source to mouth
     * @param  Collection<int, SpotSummary>  $gauged  Every gauged spot with a reading, stale ones included
     * @return list<list<array{0: string, 1: bool}>>
     */
    private function riverLevelSentences(Collection $current, Collection $gauged): array
    {
        $sentences = [];
        $groups = $current
            ->filter(fn (SpotSummary $spot) => $spot->levelRelativeToTypical !== null)
            ->groupBy('levelRelativeToTypical')
            ->sortBy(fn (Collection $group, string $relative) => array_search($relative, ['above typical range', 'within typical range', 'below typical range'], true));

        foreach ($groups as $relative => $group) {
            $sentences[] = [
                [ucfirst(LevelSummary::usualPhrase($relative) ?? ''), true],
                [' at '.$this->gaugeNames($group), false],
                ...$this->trendClause($group),
                ['.', false],
            ];
        }

        $silent = $gauged->filter(fn (SpotSummary $spot) => $spot->levelIsStale());

        if ($silent->isNotEmpty()) {
            $sentences[] = [
                ['The '.$this->gaugeNames($silent).' '.Str::plural('gauge', $silent->count()).' ', false],
                [($silent->count() === 1 ? 'has' : 'have').' not reported for '.$silent->min('levelAt')->diffForHumans(null, Carbon::DIFF_ABSOLUTE), true],
                ['.', false],
            ];
        }

        return $sentences;
    }

    /**
     * ", rising at Callander (up 0.32 m in 3 hours)", ", falling at Doune", " and steady", or nothing when the trend
     * is unknown.
     *
     * @param  Collection<int, SpotSummary>  $group
     * @return list<array{0: string, 1: bool}>
     */
    private function trendClause(Collection $group): array
    {
        $rising = $group->filter(fn (SpotSummary $spot) => $spot->levelTrend === LevelTrend::Rising)->sortByDesc(fn (SpotSummary $spot) => $spot->levelChangeLast3Hours ?? 0);
        $falling = $group->filter(fn (SpotSummary $spot) => $spot->levelTrend === LevelTrend::Falling);
        $one = $group->count() === 1;

        if ($rising->isNotEmpty()) {
            $change = $rising->first()->levelChangeLast3Hours;

            return [
                [$one ? ', and ' : ', ', false],
                [$one ? 'rising' : 'rising at '.$this->gaugeNames($rising), true],
                [$change === null ? '' : ' (up '.number_format($change, 2).' m in 3 hours)', false],
            ];
        }

        if ($falling->isNotEmpty()) {
            $change = $falling->first()->levelChangeLast3Hours;

            return [
                [$one ? ', but ' : ', ', false],
                [$one ? 'falling' : 'falling at '.$this->gaugeNames($falling), true],
                [$change === null ? '' : ' (down '.number_format(abs($change), 2).' m in 3 hours)', false],
            ];
        }

        return $group->every(fn (SpotSummary $spot) => $spot->levelTrend === LevelTrend::Steady) ? [[' and ', false], ['steady', true]] : [];
    }

    /**
     * Up to three gauge names, then "and 2 more".
     *
     * @param  Collection<int, SpotSummary>  $spots
     */
    private function gaugeNames(Collection $spots): string
    {
        $names = $spots->map(fn (SpotSummary $spot) => $spot->gaugeLabel ?? $spot->name)->unique()->values();
        $more = $names->count() - 3;

        return $more > 0
            ? $names->take(3)->join(', ').' and '.$more.' more'
            : $names->join(', ', ' and ');
    }

    /**
     * The overflows on the river and its tributaries in a sentence, as overflowSentence() does for a place.
     * Missing data never reads as no spills.
     *
     * @return list<array{0: string, 1: bool}>
     */
    private function riverOverflowSentence(RiverSummary $river, string $short, string $family, bool $covered): array
    {
        $where = "on the {$short}{$family}";

        if (! $covered) {
            return [["We do not read the water company's overflow data here yet", true], [", so storm overflows on the {$river->name} are not shown.", false]];
        }

        if ($river->monitorCount === 0) {
            return [["No storm overflow monitors {$where}", true], [" appear in the water companies' data.", false]];
        }

        if ($river->overflowDataIsStale()) {
            $updatedAt = $river->dataUpdatedAt();

            if ($updatedAt === null) {
                return [['Overflow data is out of date', true], ['.', false]];
            }

            $date = $updatedAt->copy()->timezone($this->timezone());

            return [['Overflow data is out of date', true], [': the latest from '.collect($river->companies)->join(', ', ' and ').' is from ', false], [$date->format($date->isSameYear(now()) ? 'j F' : 'j F Y'), true], ['.', false]];
        }

        $offline = $river->offlineCount;
        $working = $river->monitorCount - $offline;
        $spills = fn (int $count) => $count.' '.Str::plural('spill', $count).' in the last 48 hours';
        $offlineNote = $offline > 0 && $working > 0 ? [[" {$offline} ".Str::plural('monitor', $offline).' '.($offline === 1 ? 'is' : 'are').' offline.', false]] : [];

        if ($working === 0) {
            return [["All {$river->monitorCount} monitors {$where} are offline", true], [', so there is ', false], ['no current spill data', true], ['.', false]];
        }

        if ($river->dischargingNowCount > 0) {
            return [
                ["{$river->dischargingNowCount} of {$river->monitorCount} overflows {$where} ".($river->dischargingNowCount === 1 ? 'is' : 'are').' discharging now', true],
                ...($river->spillsLast48HoursCount > 0 ? [[', with ', false], [$spills($river->spillsLast48HoursCount), true]] : []),
                ['.', false],
                ...$offlineNote,
            ];
        }

        if ($river->spillsLast48HoursCount > 0) {
            return [[ucfirst($spills($river->spillsLast48HoursCount))." {$where}", true], [', none discharging now.', false], ...$offlineNote];
        }

        if ($offline >= $working) {
            return [
                ["Only {$working} of {$river->monitorCount} monitors {$where} ".($working === 1 ? 'is' : 'are').' reporting', true],
                [', and '.($working === 1 ? 'it' : 'they').' reported no spills in the last 48 hours.', false],
            ];
        }

        return [["No spills reported {$where} in the last 48 hours", true], ['.', false], ...$offlineNote];
    }

    private function shortRiverName(string $name): string
    {
        return preg_replace('/^(River|Afon)\s+/', '', $name) ?? $name;
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
        $fresh = $rainfall !== null && $rainfall->hasReadings() && ! $rainfall->isStale();
        [$weatherNotes, $forecastAt] = $this->weatherNotes($weather, $windy);

        return [[...$this->rainNote($fresh ? $rainfall->last24HoursMillimetres : null, null, $rainEffect), ...$weatherNotes], $forecastAt];
    }

    /**
     * A note when the rain gauge has caught heavy rain over the last day: "Heavy rain in the last 24 hours (22 mm
     * near Callander): ...". where: which gauge, where several could stand for the place.
     *
     * @return list<list<array{0: string, 1: bool}>>
     */
    private function rainNote(?float $millimetres, ?string $where, string $rainEffect): array
    {
        if ($millimetres === null || $millimetres < self::HEAVY_RAIN_MILLIMETRES) {
            return [];
        }

        return [[
            ['Heavy rain in the last 24 hours', true],
            [' ('.number_format($millimetres, 0).' mm'.($where === null ? '' : " {$where}")."): {$rainEffect}.", false],
        ]];
    }

    /**
     * Notes for heavy rain, thunder or (on open water) strong winds forecast in the next twelve hours, with when the
     * forecast was read when there is one.
     *
     * @return array{0: list<list<array{0: string, 1: bool}>>, 1: Carbon|null}
     */
    private function weatherNotes(?WeatherSummary $weather, bool $windy): array
    {
        if ($weather === null || $weather->isStale()) {
            return [[], null];
        }

        $notes = [];

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

        return [$notes, $notes === [] ? null : $weather->fetchedAt];
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

    private function timezone(): string
    {
        return config('app.display_timezone', 'Europe/London');
    }
}
