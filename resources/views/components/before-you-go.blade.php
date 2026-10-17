{{--
    General advice for getting in the water, drawn from the RNLI and the Outdoor Swimming Society, the same on every
    page of a kind. kind: river, beach or lake. It points to what to check, never says whether a place is safe.
--}}
@props(['kind'])

@php
    $points = [
        ['Cold water', 'Cold water shock can make you gasp and lose control of your breathing, even on a warm day. Get in slowly, and if you get into trouble, float on your back until your breathing settles.'],
        match ($kind) {
            'beach' => ['Currents and tides', 'Rip currents can carry even strong swimmers out to sea, and a rising tide can cut you off. At a lifeguarded beach, swim between the red and yellow flags.'],
            'lake' => ['Deep and cold', 'Lakes are often deep close to the edge and much colder below the surface. Watch for blue-green algae, which can harm people and dogs.'],
            default => ['Flow', 'Rivers can be faster than they look, especially after rain upstream, and can hide weirs, debris and sudden drops.'],
        },
        ['After heavy rain', 'Storm overflows spill most during and after heavy rain, which also washes in runoff from roads and farmland. Many swimmers wait a day or two afterwards.'],
        ['Look after yourself', 'Cover cuts, try not to swallow the water and wash your hands before eating. Swim with someone, and know how you will get out before you get in.'],
    ];
@endphp

<section {{ $attributes->merge(['class' => 'mt-10']) }}>
    <h2 class="font-display mb-3 text-lg font-semibold">Before you go in</h2>
    <div class="rounded-lg border border-line bg-surface p-5">
        <dl class="grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2">
            @foreach ($points as [$heading, $text])
                <div>
                    <dt class="font-semibold text-ink">{{ $heading }}</dt>
                    <dd class="mt-1 text-ink-2">{{ $text }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="mt-4 border-t border-line-2 pt-3 text-sm text-ink-2">
            In an emergency call 999{{ $kind === 'beach' ? ' and ask for the Coastguard' : '' }}.
            More from the <a href="https://rnli.org/safety" rel="noopener" class="text-accent hover:underline">RNLI</a>
            and the <a href="https://www.outdoorswimmingsociety.com/" rel="noopener" class="text-accent hover:underline">Outdoor Swimming Society</a>.
        </p>
    </div>
</section>
