{{--
    E. coli counts for recent samples as neutral bars, oldest on the left. Bars use a log scale fixed at 1 to 1,000 (or
    the highest count, if above), since counts run from under 10 to thousands and a fixed scale keeps low counts looking
    low. No colours or pass marks: the EA classifies over four seasons, not single samples.
--}}
@props(['samples'])

@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $ordered = $samples->filter(fn ($sample) => $sample->e_coli !== null)->sortBy('sampled_at')->values();
    $max = log10(max(1000, $ordered->max('e_coli') ?? 1000));
@endphp

@if ($ordered->count() > 1)
    <div {{ $attributes }}>
        <div class="flex h-24 items-end gap-1" role="img" aria-label="E. coli in the last {{ $ordered->count() }} samples">
            @foreach ($ordered as $sample)
                @php($height = max(4, (int) round(log10(max(1, $sample->e_coli)) / $max * 100)))
                <div class="flex-1 rounded-t-sm bg-faint/60 hover:bg-ink-2" style="height: {{ $height }}%"
                     title="{{ $sample->sampled_at->timezone($timezone)->format('j M Y') }}: E. coli {{ \App\Models\BathingWaterSample::formatCount($sample->e_coli, $sample->e_coli_qualifier) }} per 100 ml"></div>
            @endforeach
        </div>
        <div class="mt-1 flex justify-between text-xs text-muted">
            <span>{{ $ordered->first()->sampled_at->timezone($timezone)->format('j M') }}</span>
            <span>E. coli per 100 ml, log scale</span>
            <span>{{ $ordered->last()->sampled_at->timezone($timezone)->format('j M') }}</span>
        </div>
    </div>
@endif
