{{--
    Under a long list that shows its first rows only: how many of the total are shown, and a button that loads the
    next batch (SpillsOverviewService::MORE_ROWS) into the list's [data-more-rows-body] in place (more-rows.js), with
    a loader while it waits. Without JavaScript the sentence still says how many are shown.
--}}
@php
    $remaining = $total - $shown;
    $batch = \App\Services\Conditions\SpillsOverviewService::MORE_ROWS;
@endphp
@if ($remaining > 0)
    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted"
         data-more-rows="{{ $url }}" data-shown="{{ $shown }}" data-total="{{ $total }}" data-batch="{{ $batch }}">
        <span>Showing {{ $shownPhrase[0] }} <span data-more-rows-shown>{{ number_format($shown) }}</span>{{ $shownPhrase[1] }} of {{ number_format($total) }}.</span>
        <button type="button" data-more-rows-button class="font-medium text-accent hover:underline">{{ $remaining > $batch ? 'Show '.$batch.' more' : 'Show the other '.number_format($remaining) }}</button>
        <span data-more-rows-loading hidden class="inline-flex items-center gap-2 text-accent" role="status">
            <svg class="h-4 w-8 overflow-hidden" viewBox="0 0 32 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <path class="animate-wave" d="M-16 8 q4 -5 8 0 t8 0 t8 0 t8 0 t8 0 t8 0"/>
            </svg>
            Loading
        </span>
    </div>
@endif
