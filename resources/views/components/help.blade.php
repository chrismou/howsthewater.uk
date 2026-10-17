{{--
    A "?" button that explains a term. Only the button sits where the component is used, beside a heading rather than
    inside it, so headings read as their own words; the explanation goes once per term into a hidden glossary at the
    end of the page.
    tooltip.js shows it in one shared panel, kept on screen.
--}}
<button type="button"
        class="inline-flex h-[18px] w-[18px] items-center justify-center rounded-full border border-faint align-middle text-[11px] font-semibold normal-case leading-none tracking-normal text-muted hover:border-ink hover:text-ink focus:outline-none focus:ring-1 focus:ring-accent data-[open]:border-ink data-[open]:text-ink"
        aria-label="{{ $label }}"
        aria-describedby="help-{{ $term }}"
        data-help="{{ $term }}">?</button>

@pushOnce('help-glossary', 'help-'.$term)
    <p id="help-{{ $term }}">{{ $text }}</p>
@endPushOnce
