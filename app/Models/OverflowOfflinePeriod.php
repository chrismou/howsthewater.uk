<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A stretch when a monitor's feed reported it offline, from the first poll that saw it offline to the first that saw
 * it back. Feeds keep no status history, so these only exist from when we started recording them; they give each
 * monitor's uptime.
 *
 * @property int $id
 * @property int $overflow_monitor_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OverflowMonitor $overflowMonitor
 */
class OverflowOfflinePeriod extends Model
{
    protected $fillable = [
        'overflow_monitor_id',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OverflowMonitor, $this> */
    public function overflowMonitor(): BelongsTo
    {
        return $this->belongsTo(OverflowMonitor::class);
    }
}
