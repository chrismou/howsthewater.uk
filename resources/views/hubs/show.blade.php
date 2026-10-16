@php
    $copy = [
        'rivers' => [
            'title' => 'Rivers: swimming spots, storm overflows and levels',
            'description' => 'Storm overflow spills upstream, river levels and rainfall for river swimming, paddling and fishing spots in every region we cover.',
            'heading' => 'Rivers',
            'intro' => 'Each region\'s rivers run source to mouth, with spots along them showing the storm overflows upstream, the river level and recent rainfall. Pick a region to see its rivers and spots.',
        ],
        'beaches' => [
            'title' => 'Beaches: bathing water quality and storm overflows',
            'description' => 'Bathing water classifications, sample results and storm overflows nearby for every designated beach in the regions we cover.',
            'heading' => 'Beaches',
            'intro' => 'Every designated bathing water on the coast, with its yearly classification, recent samples and the storm overflows within 3 km. Pick a region to see its beaches.',
        ],
        'lakes' => [
            'title' => 'Lakes: swimming, water quality and levels',
            'description' => 'Bathing water results, lake levels, rainfall and storm overflows nearby for lake swimming and paddling in the regions we cover.',
            'heading' => 'Lakes',
            'intro' => 'Lakes with designated bathing sites or a level gauge, with their results and the storm overflows nearby. Pick a region to see its lakes.',
        ],
    ][$type];
@endphp

<x-layouts.app :title="$copy['title']" :description="$copy['description']" :breadcrumbs="[[$copy['heading'], url()->current()]]">
    <div class="mb-8 max-w-2xl">
        <p class="text-sm text-muted"><a href="{{ route('home') }}" class="hover:underline">All regions</a></p>
        <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">{{ $copy['heading'] }}</h1>
        <p class="mt-2 text-ink-2">{{ $copy['intro'] }}</p>
    </div>

    @if ($regions->isEmpty())
        <p class="text-ink-2">Nothing here yet.</p>
    @else
        <div class="grid gap-5 lg:grid-cols-2">
            @foreach ($regions as $region)
                <x-region-card :summary="$region" :type="$type" />
            @endforeach
        </div>
    @endif
</x-layouts.app>
