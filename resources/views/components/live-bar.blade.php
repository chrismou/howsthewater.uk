@props(['summary'])

@php
    $timezone = config('app.display_timezone', 'Europe/London');
    // The combined summary of every region belongs to the home page, which also holds every region's flood warnings.
    $summaryUrl = $summary->isCombined() ? route('home') : route('regions.show', $summary->slug);
    $discharging = $summary->dischargingNowCount();
    $spill24 = $summary->spotsWithSpillLast24HoursCount();
    $above = $summary->spotsAboveTypicalRange()->count();
    $floodWarnings = $summary->floodWarnings();
    $hasFloodWarning = $floodWarnings->contains(fn ($entry) => $entry['warning']->severity !== \App\Enums\FloodSeverity::FloodAlert);
    // One short count keeps the bar on one row; the breakdown by severity goes in the tooltip.
    $floodBreakdown = $floodWarnings->countBy(fn ($entry) => $entry['warning']->severity->value)->sortKeys()
        ->map(fn ($count, $level) => $count.' '.\Illuminate\Support\Str::plural(strtolower(\App\Enums\FloodSeverity::from($level)->label()), $count))
        ->join(', ');
@endphp

{{-- The region's (or every region's) headline numbers, shown under the header on every page so the state of the rivers is one glance away. --}}
<div class="bg-ink text-ground dark:bg-surface-2 dark:text-ink" data-live-bar>
    <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 text-xs sm:gap-x-7 sm:text-[13px]">
        <a href="{{ $summaryUrl }}" class="hidden font-semibold hover:underline sm:inline">{{ $summary->name }}</a>
        @if ($summary->hasOverflowData)
            <span><span class="font-mono font-semibold {{ $discharging > 0 ? 'text-alert' : '' }}">{{ $discharging }}</span> <span class="hidden sm:inline">{{ \Illuminate\Support\Str::plural('overflow', $discharging) }}</span> discharging<span class="hidden sm:inline"> now</span></span>
            <span><span class="font-mono font-semibold {{ $spill24 > 0 ? 'text-spill' : '' }}">{{ $spill24 }}</span> <span class="sm:hidden">{{ \Illuminate\Support\Str::plural('spill', $spill24) }} in 24h</span><span class="hidden sm:inline">{{ \Illuminate\Support\Str::plural('spot', $spill24) }} with a spill upstream in 24h</span></span>
        @else
            <span>Overflow data not available yet</span>
        @endif
        {{-- On phones a flood warning takes this slot, so the bar stays on one line. --}}
        <span class="{{ $floodWarnings->isNotEmpty() ? 'hidden sm:inline' : '' }}"><span class="font-mono font-semibold">{{ $above }}</span> <span class="hidden sm:inline">{{ \Illuminate\Support\Str::plural('gauge', $above) }}</span> above typical</span>
        @if ($floodWarnings->isNotEmpty())
            <a href="{{ $summaryUrl }}#flood-warnings" class="font-semibold hover:underline {{ $hasFloodWarning ? 'text-alert' : 'text-spill' }}" title="{{ $floodBreakdown }} in force">
                <span class="font-mono">{{ $floodWarnings->count() }}</span> {{ \Illuminate\Support\Str::plural($hasFloodWarning ? 'flood warning' : 'flood alert', $floodWarnings->count()) }}
            </a>
        @endif
        @if ($summary->dataUpdatedAt)
            <span class="ml-auto hidden opacity-80 sm:inline" title="Refreshed every 15 minutes">Updated <span class="font-mono">{{ $summary->dataUpdatedAt->timezone($timezone)->format('H:i') }}</span>{{ $summary->dataIsStale() ? ', out of date' : '' }}</span>
        @endif
    </div>
</div>
