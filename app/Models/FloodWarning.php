<?php

namespace App\Models;

use App\Enums\FloodSeverity;
use Database\Factories\FloodWarningFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A flood alert or warning currently in force for a flood area.
 *
 * @property int $id
 * @property int $flood_area_id
 * @property FloodSeverity $severity_level
 * @property string|null $message
 * @property Carbon|null $raised_at
 * @property Carbon|null $message_changed_at
 * @property Carbon $checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FloodArea $floodArea
 */
class FloodWarning extends Model
{
    /** @use HasFactory<FloodWarningFactory> */
    use HasFactory;

    protected $fillable = [
        'flood_area_id',
        'severity_level',
        'message',
        'raised_at',
        'message_changed_at',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'severity_level' => FloodSeverity::class,
            'raised_at' => 'datetime',
            'message_changed_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FloodArea, $this> */
    public function floodArea(): BelongsTo
    {
        return $this->belongsTo(FloodArea::class);
    }
}
