{{-- Today's EA pollution risk forecast as a pill, or why there isn't one. --}}
@props(['summary'])

@php
    $site = $summary->bathingWater;
    $risk = $summary->currentRisk();
    [$text, $classes] = match (true) {
        $risk === \App\Enums\PollutionRisk::Increased => ['Increased pollution risk today', 'bg-pill-red-bg text-pill-red-text ring-pill-red-ring'],
        $risk === \App\Enums\PollutionRisk::Normal => ['Normal pollution risk today', 'bg-pill-grey-bg text-pill-grey-text ring-pill-grey-ring'],
        ! $site->isInSeason() => ['Out of season'.($site->season_starts_on && $site->season_starts_on->isFuture() ? ' until '.$site->season_starts_on->format('j M') : ''), 'bg-pill-grey-bg text-pill-grey-text ring-pill-grey-ring'],
        default => ['No forecast today', 'bg-pill-grey-bg text-pill-grey-text ring-pill-grey-ring'],
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {$classes}"]) }}>{{ $text }}</span>
