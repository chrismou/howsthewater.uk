{{-- A bathing water's EA classification as a pill, with its year. --}}
@props(['site'])

@php
    $classes = match ($site->classification) {
        \App\Enums\BathingWaterClassification::Excellent, \App\Enums\BathingWaterClassification::Good => 'bg-pill-green-bg text-pill-green-text ring-pill-green-ring',
        \App\Enums\BathingWaterClassification::Sufficient => 'bg-pill-amber-bg text-pill-amber-text ring-pill-amber-ring',
        \App\Enums\BathingWaterClassification::Poor => 'bg-pill-red-bg text-pill-red-text ring-pill-red-ring',
        default => 'bg-pill-grey-bg text-pill-grey-text ring-pill-grey-ring',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {$classes}"]) }}>
    {{ $site->classification ? $site->classification->value.($site->classification_year ? ' · '.$site->classification_year : '') : 'Not yet classified' }}
</span>
