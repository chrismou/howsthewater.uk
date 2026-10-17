{{--
    One plain sentence naming the place, the time of the monitor data and its source, e.g. "At 08:58 BST on 16 October,
    1 of 14 storm overflow monitors upstream of Ilkley on the River Wharfe was discharging, with 3 spills in the
    previous 48 hours, according to Yorkshire Water." It reports what the monitors say, never that the water is safe.

    monitors: upstream or nearby monitor summaries (each with ->monitor). where: the place as it reads after "monitors",
    such as "upstream of Ilkley on the River Wharfe" or "within 3 km of Millport Bay".
--}}
@props(['monitors', 'where', 'dischargingNow', 'spillsLast48Hours', 'updatedAt' => null, 'covered' => true])

@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $count = $monitors->count();
    $companies = $monitors->map(fn ($summary) => preg_replace('/\s+(Ltd|Limited|plc)\.?$/i', '', $summary->monitor->company))->unique()->sort()->values();
    $when = $updatedAt ? 'At '.$updatedAt->timezone($timezone)->format('H:i T \o\n j F') : 'In the latest data';
    $sentence = match (true) {
        ! $covered => "We do not read the water company's overflow data here yet, so storm overflows {$where} are not shown.",
        $count === 0 => "No storm overflow monitors {$where} appear in the water companies' data.",
        default => null,
    } ?? sprintf('%s, %d of %d storm overflow %s %s %s discharging, with %d %s in the previous 48 hours, according to %s.',
            $when, $dischargingNow, $count, \Illuminate\Support\Str::plural('monitor', $count), $where, $dischargingNow === 1 ? 'was' : 'were',
            $spillsLast48Hours, \Illuminate\Support\Str::plural('spill', $spillsLast48Hours), $companies->join(', ', ' and '));
@endphp

<p {{ $attributes }}>{{ $sentence }}</p>
