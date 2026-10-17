<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Links an overflow to a place whose page shows it: a spot it is upstream of, or a beach or lake it is near. Rebuilt
 * from each place's conditions by regions:refresh (SyncOverflowMonitorPlaces), so an overflow's page can list the
 * places it affects without working "upstream" out backwards.
 *
 * @property int $id
 * @property int $overflow_monitor_id
 * @property string $place_type
 * @property int $place_id
 * @property int $distance_metres
 * @property-read OverflowMonitor $overflowMonitor
 * @property-read Spot|BathingWater|Lake $place
 */
class OverflowMonitorPlace extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'overflow_monitor_id',
        'place_type',
        'place_id',
        'distance_metres',
    ];

    protected function casts(): array
    {
        return [
            'distance_metres' => 'integer',
        ];
    }

    /** @return BelongsTo<OverflowMonitor, $this> */
    public function overflowMonitor(): BelongsTo
    {
        return $this->belongsTo(OverflowMonitor::class);
    }

    /** @return MorphTo<Model, $this> */
    public function place(): MorphTo
    {
        return $this->morphTo();
    }
}
