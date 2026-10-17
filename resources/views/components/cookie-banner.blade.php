@props(['measurementId'])

{{-- Hidden until the script finds no stored choice, so visitors who have chosen never see it flash. --}}
<div data-cookie-banner data-measurement-id="{{ $measurementId }}" hidden
     role="region" aria-label="Cookie consent"
     class="fixed inset-x-0 bottom-0 z-[1100] border-t border-line bg-surface shadow-lg">
    {{-- Phones: shorter copy beside the buttons, so the banner covers as little of the first screen as it can. --}}
    <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-2.5 text-sm sm:justify-between sm:py-4">
        <p class="text-xs text-ink-2 sm:text-sm">
            <span class="sm:hidden">Allow Google Analytics cookies? Nothing loads unless you accept.</span>
            <span class="hidden sm:inline">Can we use Google Analytics cookies to see how the site is used? Nothing loads unless you accept.</span>
            <a href="{{ route('privacy') }}" class="underline hover:text-ink">Privacy</a>
        </p>
        <div class="flex shrink-0 gap-2">
            <button type="button" data-cookie-reject
                    class="min-h-11 rounded-lg border border-line px-3 font-semibold text-ink hover:border-muted focus:outline-none focus:ring-1 focus:ring-accent sm:px-4">Reject</button>
            <button type="button" data-cookie-accept
                    class="min-h-11 rounded-lg bg-accent px-3 font-semibold text-surface hover:bg-accent-strong focus:outline-none focus:ring-1 focus:ring-accent sm:px-4">Accept</button>
        </div>
    </div>
</div>
