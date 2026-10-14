@props(['severity'])

@php
    $classes = match ($severity) {
        \App\Enums\FloodSeverity::SevereFloodWarning => 'border-alert bg-alert text-surface',
        \App\Enums\FloodSeverity::FloodWarning => 'border-pill-red-ring bg-pill-red-bg text-pill-red-text',
        \App\Enums\FloodSeverity::FloodAlert => 'border-pill-amber-ring bg-pill-amber-bg text-pill-amber-text',
    };
@endphp

<span {{ $attributes->class(['rounded-full border px-2.5 text-xs font-semibold', $classes]) }}>{{ $severity->label() }}</span>
