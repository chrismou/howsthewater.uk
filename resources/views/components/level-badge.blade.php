@props(['level'])

@if ($level === null || $level->latestValue === null)
    <span {{ $attributes->merge(['class' => 'text-xs text-muted']) }}>No level data</span>
@else
    @php
        $arrow = match ($level->trend) {
            \App\Enums\LevelTrend::Rising => '&uarr;',
            \App\Enums\LevelTrend::Falling => '&darr;',
            default => '&rarr;',
        };
    @endphp
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-xs text-ink-2']) }}>
        <span class="font-medium text-ink">{{ number_format($level->latestValue, 2) }} {{ $level->station->unitLabel() }}</span>
        <span aria-hidden="true">{!! $arrow !!}</span>
        <span>{{ strtolower($level->trend->label()) }}</span>
        @if ($level->relativeToUsual())
            <span class="text-faint">&middot;</span>
            <span>{{ $level->relativeToUsual() }}</span>
        @endif
        @if ($level->isStale())
            <span class="text-faint">&middot;</span>
            <span class="font-medium text-warn-text">read {{ $level->latestAt->diffForHumans() }}</span>
        @endif
    </span>
@endif
