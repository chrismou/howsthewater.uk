@php($id = 'help-'.$term.'-'.\Illuminate\Support\Str::random(6))

{{-- Opens on hover and keyboard focus through CSS; a tap or click toggles it for touch screens (see tooltip.js). --}}
<span class="group relative inline-block align-middle" data-tooltip>
    <button type="button"
            class="inline-flex h-[18px] w-[18px] items-center justify-center rounded-full border border-faint text-[11px] font-semibold leading-none text-muted hover:border-ink hover:text-ink focus:outline-none focus:ring-1 focus:ring-accent group-data-[open]:border-ink group-data-[open]:text-ink"
            aria-label="{{ $label }}"
            aria-describedby="{{ $id }}"
            data-tooltip-button>?</button>
    <span id="{{ $id }}" role="tooltip"
          class="pointer-events-none absolute left-0 top-full z-[1100] mt-2 hidden w-72 max-w-[calc(100vw-2rem)] rounded-md bg-ink px-3.5 py-2.5 text-left text-[13px] font-normal normal-case leading-snug tracking-normal text-ground shadow-lg group-hover:block group-focus-within:block group-data-[open]:block">
        {{ $text }}
    </span>
</span>
