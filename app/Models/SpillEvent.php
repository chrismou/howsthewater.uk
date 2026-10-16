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

    /**
     * How long the spill lasted (or has lasted so far), e.g. "under 1 min",
     * "20 min", "6 h 10 min" or "2 days 3 h".
     */
    public function durationLabel(?Carbon $asOf = null): string
    {
        $minutes = $this->durationMinutes($asOf);

        if ($minutes < 1) {
            return 'under 1 min';
        }

        if ($minutes < 60) {
            return "{$minutes} min";
        }

        if ($minutes < 24 * 60) {
            return sprintf('%d h %d min', intdiv($minutes, 60), $minutes % 60);
        }

        $days = intdiv($minutes, 24 * 60);

        return sprintf('%d %s %d h', $days, $days === 1 ? 'day' : 'days', intdiv($minutes % (24 * 60), 60));
    }
}
