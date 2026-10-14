@php
    $lake = $conditions->lake;
    $level = $conditions->level;
@endphp

<x-layouts.app :region="$region" :title="$lake->name.': bathing water, level and nearby overflows'"
               :description="'Environment Agency bathing water results, lake level and storm overflows near '.$lake->name.'.'">
    <div class="mb-6">
        <p class="text-sm text-muted">
            <a href="{{ route('home') }}" class="hover:underline">All regions</a>
            <span class="mx-1">/</span>
            <a href="{{ route('regions.show', $region) }}" class="hover:underline">{{ $region->name }}</a>
            <span class="mx-1">/</span>
            <a href="{{ route('lakes.index', $region) }}" class="hover:underline">Lakes</a>
        </p>
        <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">{{ $lake->name }}</h1>
        @if ($lake->description)
            <p class="mt-1 text-ink-2">{{ $lake->description }}</p>
        @endif
    </div>

    <section class="mb-8">
        <h2 class="font-display mb-3 text-lg font-semibold">Lake level <x-help term="level" /></h2>
        <div class="rounded-lg border border-line bg-surface p-5 text-sm">
            @if ($level === null)
                <p class="text-ink-2">No Environment Agency gauge reads this lake's level.</p>
            @else
                <x-level-badge :level="$level" class="text-sm" />
                <p class="mt-1 text-xs text-muted">{{ $level->station->label }} gauge{{ $level->latestAt ? ', read '.$level->latestAt->diffForHumans() : '' }}.</p>
            @endif
        </div>
    </section>

    <section class="mb-8">
        <h2 class="font-display mb-3 text-lg font-semibold">Designated bathing sites</h2>
        @forelse ($conditions->bathingWaters as $summary)
            <x-bathing-water :summary="$summary" heading class="mb-4" />
        @empty
            <p class="text-sm text-ink-2">The Environment Agency has no designated bathing sites on {{ $lake->name }}, so it is not sampled for bathing water quality.</p>
        @endforelse
    </section>

    <x-nearby-overflows :overflows="$conditions->overflows" />
</x-layouts.app>
