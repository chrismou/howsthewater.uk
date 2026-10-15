<?php

namespace App\Models;

use App\Enums\FloodSeverity;
use Database\Factories\FloodWarningEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A flood alert or warning as it was in force for one area at one severity,
 * kept after it is lifted so the history builds up.
 *
 * @property int $id
 * @property int $flood_area_id
 * @property FloodSeverity $severity_level
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property Carbon $last_seen_at
 * @property string|null $message
 * @property Carbon|null $message_changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FloodArea $floodArea
 */
class FloodWarningEvent extends Model
{
    /** @use HasFactory<FloodWarningEventFactory> */
    use HasFactory;

    protected $fillable = [
        'flood_area_id',
        'severity_level',
        'started_at',
        'ended_at',
        'last_seen_at',
        'message',
        'message_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'severity_level' => FloodSeverity::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'message_changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FloodArea, $this> */
    public function floodArea(): BelongsTo
    {
        return $this->belongsTo(FloodArea::class);
    }

    public function isOngoing(): bool
    {
        return $this->ended_at === null;
    }
}
