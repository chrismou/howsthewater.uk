{{-- A river on the levels page, at one of its spots. spot: a SpotSummary; regionName on the combined page. --}}
@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $readAt = $spot->levelAt?->copy()->timezone($timezone);
    $readAt = $readAt?->format($readAt->isSameDay(now($timezone)) ? 'H:i' : 'H:i, j M');
@endphp
<li class="flex flex-col gap-1 px-4 py-2.5 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
    <div class="min-w-0">
        <p>
            <a href="{{ route('rivers.show', [$spot->regionSlug, $spot->riverSlug]) }}" class="font-semibold hover:underline">{{ $spot->riverName }}</a>
            <span class="text-muted">at</span>
            <a href="{{ route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]) }}" class="font-semibold hover:underline">{{ $spot->name }}</a>
        </p>
        <p class="text-xs text-muted">{{ collect([$regionName, $spot->gaugeLabel ? $spot->gaugeLabel.' gauge' : null, $readAt ? 'read '.$readAt : null])->filter()->join(' · ') }}</p>
    </div>
    <p class="shrink-0 text-sm">
        <span class="font-mono font-semibold tabular-nums">{{ number_format($spot->levelValue, 2) }} {{ $spot->levelUnit }}</span>
        <span class="text-muted">{{ collect([strtolower($spot->levelTrend->label()), \App\Data\LevelSummary::usualPhrase($spot->levelRelativeToTypical)])->filter()->join(' · ') }}</span>
    </p>
</li>
