@props(['summary'])

@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $discharging = $summary->dischargingNowCount();
    $spill24 = $summary->spotsWithSpillLast24HoursCount();
    $above = $summary->spotsAboveTypicalRange()->count();
@endphp

{{-- The region's headline numbers, shown under the header on every page so the state of the rivers is one glance away. --}}
<div class="bg-ink text-ground dark:bg-surface-2 dark:text-ink" data-live-bar>
    <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-7 gap-y-1 px-4 py-2 text-xs sm:text-[13px]">
        <a href="{{ route('regions.show', $summary->slug) }}" class="font-semibold hover:underline">{{ $summary->name }}</a>
        <span><span class="font-mono font-semibold {{ $discharging > 0 ? 'text-alert' : '' }}">{{ $discharging }}</span> {{ \Illuminate\Support\Str::plural('overflow', $discharging) }} discharging now</span>
        <span><span class="font-mono font-semibold {{ $spill24 > 0 ? 'text-spill' : '' }}">{{ $spill24 }}</span> {{ \Illuminate\Support\Str::plural('spot', $spill24) }} with a spill upstream in 24h</span>
        <span><span class="font-mono font-semibold">{{ $above }}</span> {{ \Illuminate\Support\Str::plural('gauge', $above) }} above typical range</span>
        @if ($summary->dataUpdatedAt)
            <span class="ml-auto opacity-80">Updated <span class="font-mono">{{ $summary->dataUpdatedAt->timezone($timezone)->format('H:i') }}</span>{{ $summary->dataIsStale() ? ', out of date' : ' · every 15 min' }}</span>
        @endif
    </div>
</div>
