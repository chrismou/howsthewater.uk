<?php

namespace App\Models;

use Database\Factories\SpillEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $overflow_monitor_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OverflowMonitor $overflowMonitor
 */
class SpillEvent extends Model
{
    /** @use HasFactory<SpillEventFactory> */
    use HasFactory;

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

    public function isOngoing(): bool
    {
        return $this->ended_at === null;
    }

    public function durationMinutes(?Carbon $asOf = null): int
    {
        $end = $this->ended_at ?? ($asOf ?? now());

        return (int) $this->started_at->diffInMinutes($end);
    }
}
