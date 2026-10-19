@props(['recency', 'stale' => false, 'nearby' => false])

@php
    // Stale data gets the neutral style and its own words whatever it last said, so an old "no spills" never reads as
    // reassurance.
    $classes = match ($stale ? 'grey' : $recency->tone()) {
        'red' => 'bg-pill-red-bg text-pill-red-text ring-pill-red-ring',
        'amber' => 'bg-pill-amber-bg text-pill-amber-text ring-pill-amber-ring',
        'green' => 'bg-pill-green-bg text-pill-green-text ring-pill-green-ring',
        default => 'bg-pill-grey-bg text-pill-grey-text ring-pill-grey-ring',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {$classes}"]) }}
      @if ($stale) title="Monitor data is out of date" @endif>
    @if (! $stale && $recency === \App\Enums\SpillRecency::DischargingNow)
        <x-live-dot />
    @endif
    {{ $stale ? 'Overflow data out of date' : ($nearby ? $recency->nearbyLabel() : $recency->label()) }}
</span>
