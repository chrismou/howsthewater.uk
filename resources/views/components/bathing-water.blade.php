{{-- An Environment Agency designated bathing water: classification, forecast and samples. heading: show the site name. --}}
@props(['summary', 'heading' => false])

@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $site = $summary->bathingWater;
    $latest = $summary->latestSample();
    $risk = $summary->currentRisk();
    $pill = fn ($classification) => match ($classification) {
        \App\Enums\BathingWaterClassification::Excellent, \App\Enums\BathingWaterClassification::Good => 'bg-pill-green-bg text-pill-green-text ring-pill-green-ring',
        \App\Enums\BathingWaterClassification::Sufficient => 'bg-pill-amber-bg text-pill-amber-text ring-pill-amber-ring',
        \App\Enums\BathingWaterClassification::Poor => 'bg-pill-red-bg text-pill-red-text ring-pill-red-ring',
        default => 'bg-pill-grey-bg text-pill-grey-text ring-pill-grey-ring',
    };
    $count = fn ($value, $qualifier) => \App\Models\BathingWaterSample::formatCount($value, $qualifier) ?? '-';
@endphp

<section {{ $attributes->merge(['class' => 'rounded-lg border border-line bg-surface p-5']) }}>
    @if ($heading)
        <h3 class="font-display mb-3 text-lg font-semibold">{{ $site->name }}</h3>
    @endif

    <dl class="grid gap-4 sm:grid-cols-3">
        <div>
            <dt class="text-xs uppercase tracking-wide text-muted">Classification <x-help term="bathing_classification" /></dt>
            <dd class="mt-1">
                @if ($site->classification)
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-sm font-medium ring-1 ring-inset {{ $pill($site->classification) }}">{{ $site->classification->value }}</span>
                    <span class="text-sm text-muted">for {{ $site->classification_year }}</span>
                    <p class="mt-1 text-sm text-ink-2">{{ $site->classification->meaning() }}</p>
                @else
                    <span class="text-sm text-ink-2">Not yet classified.</span>
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-muted">Today's forecast <x-help term="pollution_risk" /></dt>
            <dd class="mt-1 text-sm">
                @if ($risk === \App\Enums\PollutionRisk::Increased)
                    <span class="inline-flex rounded-full bg-pill-red-bg px-2.5 py-0.5 font-medium text-pill-red-text ring-1 ring-inset ring-pill-red-ring">{{ $risk->label() }}</span>
                @elseif ($risk === \App\Enums\PollutionRisk::Normal)
                    <span class="text-ink">{{ $risk->label() }}</span>
                @elseif (! $site->isInSeason())
                    <span class="text-ink-2">No forecast outside the bathing season{{ $site->season_starts_on ? ' ('.$site->season_starts_on->format('j M').' to '.$site->season_ends_on?->format('j M').')' : '' }}.</span>
                @else
                    <span class="text-ink-2">No forecast published for today.</span>
                @endif
                @if ($site->affected_by_heavy_rain)
                    <p class="mt-1 text-ink-2">The Environment Agency says heavy rain can lower water quality here.</p>
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-muted">Latest sample <x-help term="bathing_samples" /></dt>
            <dd class="mt-1 text-sm">
                @if ($latest)
                    <p class="text-ink">{{ $latest->sampled_at->timezone($timezone)->format('j M Y') }}</p>
                    <p class="text-ink-2">E. coli <span class="font-mono">{{ $count($latest->e_coli, $latest->e_coli_qualifier) }}</span>, enterococci <span class="font-mono">{{ $count($latest->enterococci, $latest->enterococci_qualifier) }}</span> per 100 ml</p>
                @else
                    <span class="text-ink-2">No samples yet.</span>
                @endif
            </dd>
        </div>
    </dl>

    @if ($site->description)
        <p class="mt-4 text-sm text-ink-2">{{ $site->description }}</p>
    @endif

    @if ($summary->recentSamples->count() > 1)
        <details class="mt-4 text-sm">
            <summary class="cursor-pointer font-medium">Recent samples</summary>
            <table class="mt-2 w-full max-w-md text-left">
                <thead class="text-xs uppercase tracking-wide text-muted">
                    <tr><th class="py-1 font-medium">Date</th><th class="py-1 font-medium">E. coli</th><th class="py-1 font-medium">Enterococci</th></tr>
                </thead>
                <tbody class="divide-y divide-line-2 font-mono">
                    @foreach ($summary->recentSamples as $sample)
                        <tr>
                            <td class="py-1 font-sans">{{ $sample->sampled_at->timezone($timezone)->format('j M Y') }}</td>
                            <td class="py-1">{{ $count($sample->e_coli, $sample->e_coli_qualifier) }}</td>
                            <td class="py-1">{{ $count($sample->enterococci, $sample->enterococci_qualifier) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif

    @if ($summary->classifications->isNotEmpty())
        <p class="mt-3 text-sm text-ink-2">
            <span class="text-muted">Past classifications:</span>
            {{ $summary->classifications->take(6)->map(fn ($record) => $record->year.' '.$record->classification->value)->join(' · ') }}
        </p>
    @endif

    <p class="mt-3 text-xs text-muted">
        @if ($site->sewerage_undertaker)Sewerage by {{ $site->sewerage_undertaker }}. @endif
        <a href="{{ $summary->url() }}" rel="noopener" target="_blank" class="underline hover:text-ink">Environment Agency profile</a>
    </p>
</section>
