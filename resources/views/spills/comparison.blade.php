{{-- Rivers or regions side by side from the companies' annual returns for a year, most hours first. Informational:
     how places compare, never a league of "worst" ones. --}}
@php
    $withoutReturns = $totals->contains(fn ($total) => ! $total->hasReturns());
@endphp
<section id="compare" class="mb-10 scroll-mt-24">
    <div class="mb-3 flex items-center gap-1.5">
        <h2 id="compare-heading" class="font-display text-lg font-semibold">{{ $heading }}, {{ $year }}</h2>
        <x-help term="overflow_hours" />
    </div>
    <div class="overflow-x-auto rounded-lg border border-line bg-surface" role="region" aria-labelledby="compare-heading" tabindex="0">
        <table class="w-full text-sm">
            <caption class="sr-only">{{ $heading }} in {{ $year }}, by hours spilled, from the water companies' annual returns</caption>
            <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th scope="col" class="px-4 py-2.5 font-medium">{{ $nameHeading }}</th>
                    <th scope="col" class="hidden px-4 py-2.5 font-medium sm:table-cell">Overflows</th>
                    <th scope="col" class="hidden px-4 py-2.5 font-medium sm:table-cell">Counted spills</th>
                    <th scope="col" class="px-4 py-2.5 font-medium">Hours</th>
                    <th scope="col" class="px-4 py-2.5 font-medium">Hours per overflow</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line-2">
                @foreach ($totals as $total)
                    <tr>
                        <th scope="row" class="px-4 py-2.5 text-left font-normal">
                            <a href="{{ $total->url() }}" class="font-medium hover:underline">{{ $total->name }}</a>
                            @if ($total->hasReturns())
                                <span class="block text-xs text-muted sm:hidden">{{ number_format($total->countedSpills) }} counted spills</span>
                            @endif
                        </th>
                        <td class="hidden whitespace-nowrap px-4 py-2.5 font-mono tabular-nums sm:table-cell">
                            {{ number_format($total->overflows) }}
                            @if ($total->hasReturns() && $total->reporting < $total->overflows)
                                <span class="font-sans text-xs text-muted">{{ number_format($total->reporting) }} with a return</span>
                            @endif
                        </td>
                        @if ($total->hasReturns())
                            <td class="hidden px-4 py-2.5 font-mono tabular-nums sm:table-cell">{{ number_format($total->countedSpills) }}</td>
                            <td class="px-4 py-2.5 font-mono tabular-nums">{{ number_format($total->hours) }}</td>
                            <td class="px-4 py-2.5 font-mono tabular-nums">{{ number_format($total->hoursPerOverflow(), 1) }}</td>
                        @else
                            {{-- Two columns show on phones, three from sm up. --}}
                            <td colspan="2" class="px-4 py-2.5 text-muted sm:hidden">{{ $total->overflows === 0 ? 'No overflows matched' : 'No annual returns read' }}</td>
                            <td colspan="3" class="hidden px-4 py-2.5 text-muted sm:table-cell">{{ $total->overflows === 0 ? 'No overflows matched yet' : 'No annual returns read for these overflows' }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-xs text-muted">
        From the water companies' {{ $year }} annual returns to the Environment Agency, which count spills in 12 and 24 hour blocks, as published by The Rivers Trust (Open Government Licence).
        Hours per overflow divide by the overflows with a return.
        {{ $region ? 'Each river counts its tributaries, not main rivers that flow into it.' : 'Each region counts the overflows on its rivers and near its beaches and lakes.' }}
        @if ($withoutReturns)
            Scottish Water's yearly figures are not read.
        @endif
    </p>
</section>
