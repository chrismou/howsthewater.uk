@foreach ($monitors as $summary)
    <x-overflow-row :summary="$summary" :spills="$spillCounts->get($summary->monitor->id, 0)" />
@endforeach
