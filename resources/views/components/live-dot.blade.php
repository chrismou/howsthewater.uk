{{-- A bright red dot with a ring spreading from it, beside anything discharging right now, so it catches
     the eye. For visitors who ask for reduced motion it is a plain dot. --}}
<span {{ $attributes->merge(['class' => 'relative inline-flex size-1.5 shrink-0 text-alert']) }} aria-hidden="true">
    <span class="absolute inset-0 rounded-full bg-current opacity-60 motion-safe:animate-live"></span>
    <span class="relative inline-flex size-1.5 rounded-full bg-current"></span>
</span>
