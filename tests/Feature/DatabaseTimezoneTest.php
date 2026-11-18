<?php

use App\Models\OverflowMonitor;
use App\Models\SpillEvent;
use Illuminate\Support\Facades\DB;

it('stores and reads UTC times unchanged on MySQL, including the hour the UK clocks go forward', function () {
    $monitor = OverflowMonitor::factory()->create();
    $summer = SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2026-07-01 12:00:00', 'ended_at' => '2026-07-01 12:30:00']);
    $springForward = SpillEvent::factory()->create(['overflow_monitor_id' => $monitor->id, 'started_at' => '2027-03-28 01:30:00', 'ended_at' => '2027-03-28 02:00:00']);

    $stored = DB::table('spill_events')->whereIn('id', [$summer->id, $springForward->id])->orderBy('id')->selectRaw('unix_timestamp(started_at) as epoch')->pluck('epoch')->map(fn ($epoch) => (int) $epoch);

    expect($stored->all())->toBe([strtotime('2026-07-01 12:00:00 UTC'), strtotime('2027-03-28 01:30:00 UTC')])
        ->and($summer->fresh()->started_at->toDateTimeString())->toBe('2026-07-01 12:00:00');
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'Only MySQL converts TIMESTAMP columns through the session time zone');
