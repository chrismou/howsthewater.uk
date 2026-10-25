@props(['warnings'])

@php
    $timezone = config('app.display_timezone', 'Europe/London');
@endphp

{{-- Environment Agency, Natural Resources Wales or SEPA flood alerts and warnings in force for the areas covering a spot, in the agency's own words. --}}
<section class="mb-8 flex flex-col gap-3" aria-label="Flood warnings">
    @foreach ($warnings as $warning)
        @php
            $isAlert = $warning->severity === \App\Enums\FloodSeverity::FloodAlert;
            $tone = $isAlert
                ? ['box' => 'border-pill-amber-ring bg-pill-amber-bg text-pill-amber-text', 'button' => 'border-pill-amber-ring hover:border-pill-amber-text']
                : ['box' => 'border-pill-red-ring bg-pill-red-bg text-pill-red-text', 'button' => 'border-pill-red-ring hover:border-pill-red-text'];
            $updatedAt = $warning->messageChangedAt ?? $warning->raisedAt;
            $areaDescription = $warning->areaDescription ? ': '.rtrim($warning->areaDescription, '.').'.' : '.';
        @endphp
        <div class="rounded-lg border px-5 py-4 {{ $tone['box'] }}" role="alert">
            <p class="text-xs font-semibold uppercase tracking-wide">
                {{ $warning->severity->label() }} in force
                @if ($updatedAt)
                    <span class="font-mono font-normal normal-case tracking-normal"> · updated {{ $updatedAt->timezone($timezone)->format('j M, H:i') }}</span>
                @endif
            </p>
            <div class="flex items-center gap-1.5 mt-1">
                <h2 class="font-display text-xl font-semibold">{{ $warning->severity->meaning() }}</h2>
                <x-help term="flood_warning" />
            </div>
            <p class="mt-1 text-sm">
                <span class="font-semibold">{{ $warning->areaLabel }}</span>{{ $areaDescription }}
            </p>
            @if ($warning->isStale())
                <p class="mt-2 text-sm font-medium">Last confirmed {{ $warning->checkedAt->diffForHumans() }}, so it may have changed since. <x-help term="stale" /></p>
            @endif
            <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
                @if ($warning->message)
                    <details class="min-w-0 flex-1 basis-64 text-sm">
                        <summary class="cursor-pointer py-2 font-medium">Read {{ $warning->agency === \App\Enums\Agency::EnvironmentAgency ? 'the ' : '' }}{{ $warning->agency->label() }}'s message</summary>
                        <p class="mt-1 max-w-prose whitespace-pre-line">{{ $warning->message }}</p>
                    </details>
                @endif
                <a href="{{ $warning->url() }}" rel="noopener" target="_blank"
                   class="ml-auto inline-flex h-10 shrink-0 items-center gap-2 rounded-md border bg-surface px-3 text-sm font-medium focus:outline-none focus:ring-1 focus:ring-accent {{ $tone['button'] }}">
                    {{ $warning->urlLabel() }}
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6"/><path d="M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>
                </a>
            </div>
        </div>
    @endforeach
</section>
