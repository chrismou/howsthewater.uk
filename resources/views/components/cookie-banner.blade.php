@props(['measurementId'])

{{-- Hidden until the script finds no stored choice, so visitors who have chosen never see it flash. --}}
<div data-cookie-banner data-measurement-id="{{ $measurementId }}" hidden
     role="region" aria-label="Cookie consent"
     class="fixed inset-x-0 bottom-0 z-[1100] border-t border-line bg-surface shadow-lg">
    <div class="mx-auto flex max-w-5xl flex-col gap-3 px-4 py-4 text-sm sm:flex-row sm:items-center sm:justify-between">
        <p class="text-ink-2">
            Can we use Google Analytics cookies to see how the site is used? Nothing loads unless you accept.
            <a href="{{ route('privacy') }}" class="underline hover:text-ink">Privacy</a>
        </p>
        <div class="flex shrink-0 gap-2">
            <button type="button" data-cookie-reject
                    class="min-h-10 rounded-lg border border-line px-4 font-semibold text-ink hover:border-muted focus:outline-none focus:ring-1 focus:ring-accent">Reject</button>
            <button type="button" data-cookie-accept
                    class="min-h-10 rounded-lg bg-accent px-4 font-semibold text-surface hover:bg-accent-strong focus:outline-none focus:ring-1 focus:ring-accent">Accept</button>
        </div>
    </div>
</div>
