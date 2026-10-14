@props(['level'])

@if ($level === null || $level->latestValue === null)
    <span {{ $attributes->merge(['class' => 'text-xs text-stone-500']) }}>No level data</span>
@else
    @php
        $arrow = match ($level->trend) {
            \App\Enums\LevelTrend::Rising => '&uarr;',
            \App\Enums\LevelTrend::Falling => '&darr;',
            default => '&rarr;',
        };
    @endphp
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-xs text-stone-600']) }}>
        <span class="font-medium text-stone-900">{{ number_format($level->latestValue, 2) }} {{ $level->station->unitLabel() }}</span>
        <span aria-hidden="true">{!! $arrow !!}</span>
        <span>{{ strtolower($level->trend->label()) }}</span>
        @if ($level->relativeToTypical)
            <span class="text-stone-400">&middot;</span>
            <span>{{ $level->relativeToTypical }}</span>
        @endif
        @if ($level->isStale())
            <span class="text-stone-400">&middot;</span>
            <span class="font-medium text-amber-700">read {{ $level->latestAt->diffForHumans() }}</span>
        @endif
    </span>
@endif
