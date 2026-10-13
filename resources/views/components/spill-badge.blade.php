@props(['recency'])

@php
    $classes = match ($recency) {
        \App\Enums\SpillRecency::DischargingNow => 'bg-red-100 text-red-800 ring-red-200',
        \App\Enums\SpillRecency::Within24Hours => 'bg-orange-100 text-orange-800 ring-orange-200',
        \App\Enums\SpillRecency::Within48Hours => 'bg-amber-100 text-amber-800 ring-amber-200',
        \App\Enums\SpillRecency::None => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        \App\Enums\SpillRecency::NoData => 'bg-stone-100 text-stone-600 ring-stone-200',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {$classes}"]) }}>
    {{ $recency->label() }}
</span>
