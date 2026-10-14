{{-- The key for maps showing bathing waters by classification. place: name a place marker (a lake); overflows: list overflow colours. --}}
@props(['overflows' => true, 'place' => null])

<ul {{ $attributes->merge(['class' => 'mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted']) }} aria-label="Map key">
    @if ($place)
        <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-accent"></span>{{ $place }}</li>
    @endif
    <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#047857"></span>Excellent or Good</li>
    <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#b45309"></span>Sufficient</li>
    <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#b91c1c"></span>Poor</li>
    @if ($overflows)
        <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full" style="background:#b91c1c"></span>Overflow discharging</li>
        <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full" style="background:#64748b"></span>Overflow</li>
        <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full" style="background:#a8a29e"></span>Offline</li>
    @endif
</ul>
