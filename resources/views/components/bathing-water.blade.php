{{-- A designated bathing water as a compact card: classification, forecast, latest sample and sample history. --}}
@props(['summary', 'heading' => true])

@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $site = $summary->bathingWater;
    $latest = $summary->latestSample();
    $count = fn ($value, $qualifier) => \App\Models\BathingWaterSample::formatCount($value, $qualifier) ?? '-';
@endphp

<article {{ $attributes->merge(['class' => 'flex flex-col gap-3 rounded-lg border border-line bg-surface p-5']) }}>
    @if ($heading)
        <h3 class="font-display text-lg font-semibold">{{ $site->name }}</h3>
    @endif

    <div class="flex flex-wrap items-center gap-2">
        <x-classification-pill :site="$site" />
        <x-bathing-forecast :summary="$summary" />
        <x-help term="bathing_classification" />
    </div>

    <p class="text-sm text-ink-2">
        @if ($latest)
            Latest sample {{ $latest->sampled_at->timezone($timezone)->format('j M Y') }}:
            E. coli <span class="font-mono">{{ $count($latest->e_coli, $latest->e_coli_qualifier) }}</span>,
            enterococci <span class="font-mono">{{ $count($latest->enterococci, $latest->enterococci_qualifier) }}</span> per 100 ml.
            <x-help term="bathing_samples" />
        @elseif (! $summary->hasSamplesHere())
            Sample results are on the <a href="{{ $summary->url() }}" rel="noopener" target="_blank" class="underline hover:text-ink">{{ $summary->urlLabel() }}</a>.
        @else
            No samples yet.
        @endif
    </p>

    <x-sample-bars :samples="$summary->recentSamples" />

    <details class="text-sm">
        <summary class="cursor-pointer font-medium text-ink-2">Classifications and description</summary>
        <div class="mt-2 flex flex-col gap-2 text-ink-2">
            @if ($site->classification)
                <p>{{ $site->classification->meaning() }}</p>
            @endif
            @if ($summary->classifications->isNotEmpty())
                <p><span class="text-muted">Past years:</span> {{ $summary->classifications->take(6)->map(fn ($record) => $record->year.' '.$record->classification->value)->join(' · ') }}</p>
            @endif
            @if ($site->description)
                <p>{{ $site->description }}</p>
            @endif
            <p class="text-xs text-muted">
                @if ($site->sewerage_undertaker)Sewerage by {{ $site->sewerage_undertaker }}. @endif
                <a href="{{ $summary->url() }}" rel="noopener" target="_blank" class="underline hover:text-ink">{{ $summary->urlLabel() }}</a>
            </p>
        </div>
    </details>
</article>
