{{-- Overflows discharging now, in the order chosen above the table, with the places their discharge reaches. The
     first rows only, with a control that loads the rest in batches. --}}
<div data-more-rows-list>
    <div class="overflow-x-auto rounded-lg border border-line bg-surface">
        <table class="w-full text-sm">
            <caption class="sr-only">{{ $label }}, {{ $order === \App\Enums\DischargingOrder::Newest ? 'latest to start first' : 'longest running first' }}</caption>
            <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th scope="col" class="px-4 py-2.5 font-medium">Overflow</th>
                    <th scope="col" class="hidden px-4 py-2.5 font-medium sm:table-cell">Into</th>
                    <th scope="col" class="px-4 py-2.5 font-medium">Since</th>
                    <th scope="col" class="hidden px-4 py-2.5 font-medium md:table-cell">{{ $placesHeading }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line-2" data-more-rows-body>
                @include('spills.discharging-rows')
            </tbody>
        </table>
    </div>
    @include('spills.more-rows', [
        'url' => $moreUrl,
        'shown' => $listings->count(),
        'total' => $total,
        'shownPhrase' => $order === \App\Enums\DischargingOrder::Newest ? ['the latest', ''] : ['the', ' longest running'],
    ])
</div>
