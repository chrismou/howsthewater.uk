<?php

namespace App\Models;

use App\Data\SpotConditions;
use App\Enums\OverflowStatus;
use Database\Factories\OverflowMonitorFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $external_id
 * @property int|null $river_id
 * @property string $company
 * @property string|null $receiving_watercourse
 * @property float $latitude
 * @property float $longitude
 * @property int|null $river_metres
 * @property OverflowStatus $status
 * @property Carbon|null $status_started_at
 * @property Carbon|null $latest_event_started_at
 * @property Carbon|null $latest_event_ended_at
 * @property Carbon|null $source_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read River|null $river
 * @property-read Collection<int, SpillEvent> $openSpillEvents
 */
class OverflowMonitor extends Model
{
    /** @use HasFactory<OverflowMonitorFactory> */
    use HasFactory;

    protected $fillable = [
        'external_id',
        'river_id',
        'company',
        'receiving_watercourse',
        'latitude',
        'longitude',
        'river_metres',
        'status',
        'status_started_at',
        'latest_event_started_at',
        'latest_event_ended_at',
        'source_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'status' => OverflowStatus::class,
            'status_started_at' => 'datetime',
            'latest_event_started_at' => 'datetime',
            'latest_event_ended_at' => 'datetime',
            'source_updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<River, $this> */
    public function river(): BelongsTo
    {
        return $this->belongsTo(River::class);
    }

    /** @return HasMany<SpillEvent, $this> */
    public function spillEvents(): HasMany
    {
        return $this->hasMany(SpillEvent::class)->orderByDesc('started_at');
    }

    /**
     * Events the feed has not reported an end for. Normally at most one.
     *
     * @return HasMany<SpillEvent, $this>
     */
    public function openSpillEvents(): HasMany
    {
        return $this->hasMany(SpillEvent::class)->whereNull('ended_at');
    }

    public function isDischarging(): bool
    {
        return $this->status === OverflowStatus::Discharging;
    }

    /**
     * Whether the feed has not confirmed this monitor's status within SpotConditions::STALE_AFTER_HOURS, so its
     * status is only what it was at the last report.
     */
    public function isStale(): bool
    {
        return $this->source_updated_at === null
            || $this->source_updated_at->lessThan(now()->subHours(SpotConditions::STALE_AFTER_HOURS));
    }
}
