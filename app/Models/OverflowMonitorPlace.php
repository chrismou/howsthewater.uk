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
 * @property-read Spot|BathingWater|Lake|null $place Null if the place has been deleted since the last refresh
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

    /**
     * The place's page. Load the place with its river and region (RegionOverflows does).
     */
    public function placeUrl(): ?string
    {
        $place = $this->place;

        return match (true) {
            $place instanceof Spot => route('spots.show', [$place->river->region, $place->river, $place]),
            $place instanceof BathingWater => route('beaches.show', [$place->region, $place]),
            $place instanceof Lake => route('lakes.show', [$place->region, $place]),
            default => null,
        };
    }

    /**
     * The place's name, with its river for a spot ("Ilkley, River Wharfe").
     */
    public function placeLabel(): string
    {
        $place = $this->place;

        return $place instanceof Spot ? "{$place->name}, {$place->river->name}" : (string) $place?->name;
    }

    /**
     * How far the overflow is from the place: downstream along the water for a spot, in a straight line for a beach
     * or lake.
     */
    public function distanceLabel(): string
    {
        return number_format($this->distance_metres / 1000, 1).' km'.($this->place instanceof Spot ? ' downstream' : ' away');
    }
}
