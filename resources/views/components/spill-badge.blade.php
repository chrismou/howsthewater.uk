@props(['recency', 'stale' => false])

@php
    // Stale data gets the neutral style whatever it says, so an old "no spills" never reads as reassurance.
    $classes = $stale ? 'bg-pill-grey-bg text-pill-grey-text ring-pill-grey-ring' : match ($recency) {
        \App\Enums\SpillRecency::DischargingNow => 'bg-pill-red-bg text-pill-red-text ring-pill-red-ring',
        \App\Enums\SpillRecency::Within24Hours => 'bg-pill-amber-bg text-pill-amber-text ring-pill-amber-ring',
        \App\Enums\SpillRecency::Within48Hours => 'bg-pill-amber-bg text-pill-amber-text ring-pill-amber-ring',
        \App\Enums\SpillRecency::None => 'bg-pill-green-bg text-pill-green-text ring-pill-green-ring',
        \App\Enums\SpillRecency::Offline => 'bg-pill-grey-bg text-pill-grey-text ring-pill-grey-ring',
        \App\Enums\SpillRecency::NoData => 'bg-pill-grey-bg text-pill-grey-text ring-pill-grey-ring',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {$classes}"]) }}
      @if ($stale) title="Monitor data is out of date" @endif>
    {{ $recency->label() }}
</span>
